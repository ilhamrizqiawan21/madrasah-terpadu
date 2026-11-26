# PRD — Madrasah Terpadu (Darul Istiqamah Kolaka)

Status: **Draf 1, 2026-11-26** · Pemilik produk: Ilham Rizqiawan · Klien: Yayasan Amal Jaya Berkah (MI, RA, TPQ Darul Istiqamah, Kolaka)

Dokumen ini adalah **sumber kebenaran perilaku produk**. Bila kode, TODO, atau tebakan agen bertentangan dengan PRD, PRD yang menang.
Bagian bertanda **[TERBUKA]** belum diputuskan klien: jangan diimplementasikan sebelum ada keputusan di `DECISIONS.md`.

---

## 1. Masalah dan tujuan

Sekolah sudah memakai aplikasi ERP pihak lain (YAZSCHOOL ERP v2.4). Klien menyukai fleksibilitas tagihannya, tetapi:

- hak akses tidak konsisten (wali bisa membuka halaman admin, menu wali menampilkan link yang ditolak);
- data tidak sinkron (saldo e-wallet di dashboard berbeda dengan halaman e-wallet);
- pembayaran online masih memakai mode sandbox Midtrans di produksi;
- belum ada PWA, notifikasi ke HP orang tua, mekanisme poin kedisiplinan yang terakumulasi, dan reward.

Aplikasi ini dibangun **dari nol** (tidak ada akses ke source code aplikasi lama).

**Tujuan utama (urutan prioritas dari klien):**

1. **Integrasi ke orang tua**: setiap kejadian penting tentang anak sampai ke HP wali secara cepat dan bisa dipercaya.
2. **Integrasi pembayaran**: tagihan sekolah dan isi saldo kantin dibayar online lewat Midtrans, tercatat benar tanpa rekonsiliasi manual.
3. **Pembinaan karakter**: Buku Saku BK (poin pelanggaran dan kebaikan), pembiasaan baik (mutabaah), setoran hafalan, dan nilai sikap sosial-spiritual yang diturunkan dari data tersebut.

**Ukuran keberhasilan:**

- ≥ 80% wali aktif memasang PWA dan mengizinkan notifikasi dalam 1 bulan setelah rilis.
- Notifikasi presensi tiba ≤ 60 detik setelah tap (p95).
- 0 selisih antara status tagihan di aplikasi dan status transaksi di dashboard Midtrans pada rekonsiliasi bulanan.
- 0 temuan "akses ditolak tapi menu tampil" atau "wali bisa membuka data anak lain" pada UAT.

## 2. Bukan tujuan (fase ini)

PPDB online, presensi biometrik/sidik jari, e-learning (tugas, materi), perpustakaan, asrama dan perizinan asrama, transportasi,
helpdesk tiket, WhatsApp API (disiapkan sebagai channel tambahan, tidak diaktifkan), multi-yayasan/multi-tenant, aplikasi native Android/iOS, website profil sekolah/CMS.
Semua ini boleh menjadi fase lanjutan dengan kesepakatan terpisah. **Agen tidak boleh membangun modul di luar daftar ini.**

## 3. Pengguna dan peran

| Peran             | Siapa                         | Akses pokok                                                                       |
| ----------------- | ----------------------------- | --------------------------------------------------------------------------------- |
| `admin`           | Operator yayasan              | Semua data induk, pengaturan, pengguna, laporan semua unit                        |
| `bendahara`       | Bendahara unit/yayasan        | Jenis tagihan, penerbitan tagihan, kasir, rekonsiliasi Midtrans, laporan keuangan |
| `guru`            | Guru mapel/tahfidz/wali kelas | Mutabaah (verifikasi), setoran hafalan, catat perilaku, nilai sikap kelasnya      |
| `bk`              | Guru BK/kesiswaan             | Buku Saku BK penuh, aturan poin, ambang tindakan, persetujuan reward              |
| `petugas_gerbang` | Satpam/piket                  | Halaman scan presensi saja                                                        |
| `kasir_kantin`    | Penjaga kantin                | Halaman POS kantin saja                                                           |
| `wali`            | Orang tua/wali                | **Hanya** data anak yang terhubung dengannya                                      |
| `kepala_unit`     | Kepala MI/RA/TPQ              | Baca semua data unitnya, laporan, nilai sikap final                               |

Satu pengguna boleh memiliki lebih dari satu peran (mis. guru sekaligus wali kelas). "Wali kelas" bukan peran, melainkan penugasan guru ke rombel.
Data dibatasi per **unit** (MI/RA/TPQ) kecuali untuk `admin` dan `bendahara` yayasan.

**Siswa** bukan pengguna aplikasi umum. Siswa hanya masuk ke **halaman ujian CBT** (§4.14) dengan NIS + PIN ujian, lewat guard terpisah, tanpa akses ke menu lain (ADR-020).

## 4. Konsep domain dan aturan bisnis

### 4.1 Struktur sekolah

- **Unit**: MI, RA, TPQ. Setiap siswa, rombel, jenis tagihan, aturan poin, dan item mutabaah milik satu unit (aturan poin/mutabaah boleh berlaku untuk semua unit).
- **Tahun ajaran** + **semester** (Ganjil/Genap). Tepat satu tahun ajaran+semester aktif.
- **Rombel** (kelas): milik unit dan tahun ajaran, punya tingkat (mis. 1–6 untuk MI, A/B untuk RA) dan satu wali kelas.
- **Siswa** terdaftar di satu rombel per tahun ajaran (riwayat disimpan).
- **Wali**: akun orang tua. Satu wali boleh punya banyak anak; satu anak boleh punya beberapa wali (ayah, ibu). Semua wali yang terhubung menerima notifikasi.

### 4.2 Presensi gerbang (berangkat dan pulang)

- Setiap siswa punya **kartu QR** berisi token acak (bukan NIS) yang bisa dicabut dan diterbitkan ulang.
- Petugas gerbang men-scan kartu dengan **scanner barcode USB/Bluetooth** (masuk sebagai ketikan + Enter) atau **kamera HP/tablet**.
- Scan pertama hari itu = **tap masuk**; scan berikutnya setelah jam pulang minimal = **tap pulang**. Scan ganda < 2 menit diabaikan dengan umpan balik "sudah tercatat".
- Status harian: `hadir`, `terlambat` (tap masuk setelah batas jam masuk unit), `izin`, `sakit`, `alpa` (tanpa tap dan tanpa izin setelah jam batas alpa).
- Jam masuk, batas terlambat, jam pulang minimal, dan batas alpa diatur per unit. Hari libur dan tanggal merah diatur di kalender.
- Wali dapat mengajukan **izin/sakit** (dengan lampiran foto opsional) untuk tanggal tertentu; wali kelas/admin menyetujui. Izin yang disetujui menimpa status alpa.
- **Notifikasi ke wali**: saat tap masuk ("Ananda Dais tiba di sekolah 06.52"), tap pulang, dan saat status alpa ditetapkan.

### 4.3 Notifikasi ke orang tua

- Channel utama: **Web Push** melalui PWA. Juga tersimpan sebagai **kotak masuk** di aplikasi (database), sehingga tidak ada notifikasi yang hilang meski push gagal.
- Wali dapat mengatur jenis notifikasi yang ingin diterima per kategori (presensi, kantin, perilaku, tagihan, mutabaah). Notifikasi tagihan lunas dan keamanan akun selalu aktif.
- Channel WhatsApp disiapkan sebagai antarmuka (`Channel` kosong yang bisa diaktifkan) tetapi **tidak diaktifkan** di fase ini.
- Notifikasi tidak memuat data sensitif selain nama panggilan anak dan nominal; isi lengkap dibuka di aplikasi setelah login.

### 4.4 Keuangan sekolah (tagihan)

- **Jenis tagihan**: nama, unit, sifat `bulanan` atau `sekali`, nominal default, akun pos (mis. SPP, Uang Pangkal, Kegiatan).
- **Penerbitan tagihan** dapat ditujukan ke: satu unit, satu tingkat, satu rombel, beberapa siswa terpilih, atau satu siswa. Untuk `bulanan`, pilih rentang bulan; sistem membuat satu tagihan per siswa per bulan.
- Nominal dapat diubah per siswa saat penerbitan (mis. keringanan) dan setelahnya melalui **penyesuaian** (diskon/pembebasan) yang tercatat dan diaudit; tagihan tidak pernah diedit diam-diam.
- Satu kombinasi siswa + jenis tagihan + periode hanya boleh terbit sekali (idempoten). Penerbitan ulang menampilkan pratinjau "sudah ada / akan dibuat".
- **Pembayaran**: tunai/transfer manual oleh bendahara (boleh cicilan/sebagian), atau **online Midtrans** oleh wali (pelunasan penuh per tagihan; wali boleh memilih beberapa tagihan sekaligus).
- Status tagihan: `belum_bayar`, `sebagian`, `lunas`, `dibatalkan`. Lewat jatuh tempo dan belum lunas = tampil "menunggak" (turunan, bukan status tersimpan).
- Pembatalan pembayaran manual hanya oleh bendahara dengan alasan, menghasilkan entri pembalik (tidak menghapus).
- Kuitansi digital (PDF) untuk setiap pembayaran.
- **Syarat ujian** (ADR-016): bendahara/admin membuat **periode ujian** (unit, nama, `cbt` atau `kertas`, tanggal) dan memilih tagihan yang wajib lunas (mis. SPP s.d. bulan tertentu + tagihan lain sesuai kebijakan). Siswa **layak** bila semua tagihan yang cocok lunas; cicilan sebagian belum layak. **Dispensasi** hanya oleh admin, beralasan, per periode, diaudit. Kelayakan dihitung saat dibaca, sehingga pembayaran yang masuk langsung berlaku. CBT: siswa tidak layak tidak bisa memulai ujian (pesan netral "Hubungi admin sekolah"). Kertas: tanpa blokir, panitia menerima daftar peringatan per rombel. Wali mendapat kartu "Perlu tindakan" + notifikasi H-7 dan H-1. Berlaku untuk MI lebih dulu.

### 4.5 Pembayaran online (Midtrans)

- Akun Midtrans milik yayasan; dana masuk langsung ke rekening yayasan. Aplikasi hanya menyimpan Server Key/Client Key di `.env`.
- Midtrans dipakai **hanya untuk uang masuk**: pelunasan tagihan dan isi saldo kantin. Jajan kantin tidak lewat Midtrans.
- Status lunas/saldo bertambah **hanya** dari webhook Midtrans yang terverifikasi (lihat `ARCHITECTURE.md` §6), tidak pernah dari callback browser.
- Biaya admin (MDR) **[TERBUKA]**: ditanggung yayasan atau dibebankan ke wali sebagai item terpisah. Default sampai diputuskan: ditanggung yayasan (tidak ada item biaya).
- Metode yang diaktifkan default: QRIS, GoPay, ShopeePay, VA (BCA, BNI, BRI, Permata, Mandiri bill). Diatur via konfigurasi.
- Order yang tidak dibayar kedaluwarsa setelah 24 jam; tagihannya kembali bisa dibayar.

### 4.6 Dompet kantin

- Setiap siswa punya satu **dompet** dengan saldo uang dan **limit jajan harian** (diatur wali, batas atas diatur sekolah).
- Isi saldo: online oleh wali (Midtrans) atau tunai di bendahara/kasir yang diberi izin.
- **POS kantin**: kasir men-scan kartu QR siswa, memasukkan total belanja (opsional rincian item), sistem memotong **voucher reward dulu, lalu saldo uang**. Ditolak bila saldo kurang atau melewati limit harian.
- Setiap transaksi jajan mengirim notifikasi ke wali ("Ananda jajan Rp8.000 di Kantin MI. Sisa saldo Rp42.000").
- Koreksi transaksi (salah input) hanya oleh kasir dalam 15 menit atau oleh bendahara kapan pun, berupa entri pembalik.
- Saldo dihitung dari **ledger** (lihat ADR-006). Tidak ada kolom saldo yang ditimpa tanpa entri.

### 4.7 Buku Saku BK (berbasis SIPESAN)

- **Aturan poin** (master): kode, nama, kategori (`pelanggaran`, `prestasi`, `kebaikan`), bobot poin, unit, aktif/nonaktif. Diturunkan dari tabel `jenis_pelanggaran` dan `tingkat_prestasi` di SIPESAN.
- **Catatan perilaku**: guru/BK mencatat siswa + aturan + tanggal + keterangan + bukti foto opsional. Poin disalin (snapshot) dari aturan saat dicatat, sehingga perubahan bobot tidak mengubah riwayat.
- Dua akun poin per siswa per tahun ajaran:
    - **Poin pelanggaran** (bertambah) → memicu **ambang tindakan**.
    - **Poin kebaikan** (dari prestasi dan kebaikan) → dapat **ditukar reward**.
- **Ambang tindakan** (master per unit): mis. 25 = teguran lisan, 50 = SP1 + pemberitahuan wali, 75 = SP2 + panggilan orang tua, 100 = SP3. Saat ambang terlewati, sistem membuat **tindak lanjut** yang harus ditandai selesai oleh BK, dan mengirim notifikasi ke wali sesuai konfigurasi ambang.
- Keterlambatan dari presensi gerbang **[TERBUKA]**: otomatis menjadi catatan pelanggaran atau tidak. Default: tidak otomatis; BK dapat membuat catatan dari daftar keterlambatan dengan satu klik.
- Pembatalan catatan: hanya BK/admin, dengan alasan, menghasilkan entri poin pembalik.
- Wali melihat riwayat catatan anaknya (tanpa nama pencatat bila diatur sekolah) dan menerima notifikasi tiap catatan.
- Poin direset per tahun ajaran (akun baru), riwayat tetap tersimpan.

### 4.8 Reward (tukar poin kebaikan)

- **Penawaran reward** (master): nama, biaya poin, nilai voucher kantin (Rp), unit, stok opsional, masa berlaku voucher (hari), aktif/nonaktif.
- Ketika poin kebaikan siswa ≥ biaya penawaran termurah yang aktif, siswa/wali melihat **penawaran tersedia** dan mendapat notifikasi sekali per penawaran.
- Penukaran diajukan wali (atau BK atas nama siswa) dan **disetujui BK**. Persetujuan mengurangi poin kebaikan dan menerbitkan **voucher kantin**.
- Voucher hanya dapat dipakai di POS kantin, tidak bisa dicairkan, kedaluwarsa sesuai masa berlaku. Nilai voucher adalah subsidi sekolah dan dilaporkan terpisah dari uang wali.

### 4.9 Pembiasaan baik (mutabaah)

- **Item pembiasaan** (master, dikelola admin): nama (mis. Tilawah, Membantu orang tua), aspek (`spiritual` atau `sosial`), frekuensi harian, unit, urutan, aktif.
- **Sholat 5 waktu** (Subuh, Dzuhur, Ashar, Maghrib, Isya) adalah item **terkunci** bawaan: tidak dapat dihapus, dinonaktifkan, atau diganti nama; admin hanya mengatur urutan (ADR-019). Item lain bebas ditambah atau dinonaktifkan (item yang punya catatan tidak dihapus).
- Wali mencentang item untuk anaknya per hari (maks. 3 hari ke belakang); guru/wali kelas **memverifikasi** atau menolak dengan catatan. Hanya data terverifikasi yang dipakai untuk nilai sikap.
- Konsistensi (mis. 7 hari berturut-turut item tertentu) dapat menghasilkan poin kebaikan otomatis bila aturan streak diaktifkan **[TERBUKA]** (default: nonaktif).

### 4.10 Setoran hafalan (tahfidz) dan praktik ibadah

- **Tiga jenis hafalan** (ADR-017): **Qur'an**, **Hadits**, **Do'a**. Materi hadits dan doa (judul, teks Arab, terjemahan, sumber/perawi) diinput **admin**. Setoran hadits/doa memilih satu materi; **ziyadah/murajaah hanya untuk Qur'an**. Target hadits/doa per tingkat berupa daftar materi; progres = materi lulus / materi target. Wali melihat progres per jenis.
- **Tahfidz**: guru mencatat setoran `ziyadah` (hafalan baru) atau `murajaah` (ulangan): rentang surah:ayat awal–akhir, nilai per kriteria rubrik (kelancaran, tajwid, makhraj), nilai akhir, predikat, catatan. Rubrik berversi; versi terbit tidak dapat diubah (port dari `penilaian-tahfidz`).
- Target hafalan per tingkat (mis. Juz 30 untuk MI). Progres = ayat unik yang sudah lulus ziyadah dibanding target.
- Data mushaf hanya **daftar surah + jumlah ayat** (data publik). Tidak ada tampilan teks mushaf di fase ini.
- **Praktik ibadah (PIB)**: materi berjenjang (bab → subbab → materi) dengan bobot; guru memasukkan jumlah kesalahan; **nilai = 90 − kesalahan** (port aturan dari `pib-laravel`).
- Wali melihat progres hafalan dan riwayat setoran, dan mendapat notifikasi tiap setoran.
- **Penghitung kesalahan** (ADR-018) untuk kriteria rubrik mode pengurangan dan nilai PIB: di laptop **PageDown = tambah 1, PageUp = kurang 1** pada kriteria aktif (cocok dengan clicker presenter); di HP tombol − / + ≥ 44 px. Tidak bisa di bawah 0 atau melewati batas; nilai dihitung ulang di server.

### 4.11 Nilai sikap sosial dan spiritual

- Dihitung per siswa per semester, lalu **dikoreksi dan dikunci oleh wali kelas**, disetujui kepala unit.
- Rumus usulan **[TERBUKA — wajib dikonfirmasi klien sebelum Fase 12]**:
    - Spiritual = persentase pembiasaan aspek spiritual yang terverifikasi terhadap yang diharapkan (hari efektif × item aktif).
    - Sosial = 100 − poin pelanggaran semester + min(poin kebaikan semester, 10), dibatasi 0–100.
    - Predikat: A ≥ 90, B ≥ 80, C ≥ 70, D < 70.
    - Deskripsi otomatis disusun dari item/aturan terkuat dan terlemah; wali kelas boleh mengubah.
- Rumus dan ambang disimpan sebagai konfigurasi, bukan hardcode.

### 4.12 Portal wali

Layar pertama wali menjawab satu pertanyaan: **"Bagaimana anak saya hari ini, dan apa yang perlu saya lakukan?"**

- Kartu per anak: status presensi hari ini, saldo kantin + jajan hari ini, tagihan yang jatuh tempo, catatan perilaku terbaru, mutabaah hari ini yang belum dicentang.
- Aksi utama: bayar tagihan, isi saldo, centang mutabaah, ajukan izin.
- Riwayat: presensi, transaksi kantin, tagihan & kuitansi, perilaku & poin, hafalan, nilai sikap (setelah dikunci).
- Kotak masuk notifikasi dan pengaturan notifikasi.

### 4.13 Pengaturan dan branding

Nama aplikasi, nama yayasan dan unit, logo, favicon, warna utama (dengan turunan otomatis dan pemeriksaan kontras), alamat, kontak, jam presensi per unit,
kalender libur, konfigurasi Midtrans (mode sandbox/production dibaca dari `.env`, ditampilkan read-only), konfigurasi notifikasi.

### 4.14 Ujian CBT (ADR-020)

- Dibangun di aplikasi ini, merujuk `CBT-Sekolah` (REUSE_MAP §5). Unit pertama **MI**. Server sama dengan aplikasi utama.
- **Data**: mapel per unit, penugasan guru × mapel × rombel (memakai data induk), bank soal per ujian: **pilihan ganda**, **menjodohkan** (benar semua = poin penuh, selain itu 0), **essay** (dikerjakan di kertas, dinilai guru per soal). Bobot per tipe diatur per ujian (bawaan 2/2/4).
- **Nilai** = total poin / poin maksimal × 100, dibulatkan. Status `menunggu_essay` sampai essay dinilai. Hasil tampil ke wali bila diizinkan per ujian (bawaan: tidak).
- **Ujian**: mapel, rombel/tingkat, jendela waktu, durasi, acak soal/pilihan, token opsional, batas pelanggaran, periode ujian (§4.4) opsional, **mode perangkat** yang diizinkan.
- **Mode perangkat** (dipilih admin per ujian, tanpa ubah kode): `lab` (layar penuh wajib, pelanggaran mengunci setelah batas), `tablet` (layar penuh bila didukung, pindah aplikasi dihitung), `phone` HP orang tua (pelanggaran dicatat saja, Exambro opsional).
- **Siswa** masuk dengan **NIS + PIN ujian** (di-hash, dibagikan wali kelas, bisa di-reset); rate limit per NIS. Satu soal per layar, tombol besar, tanda ragu-ragu, teks Arab RTL dengan kontrol ukuran.
- **Ketahanan**: timer dihitung server; login ulang melanjutkan sesi dan mengambil alih perangkat lama; jawaban disimpan lokal lalu disinkron batch idempoten (grace 120 detik); sesi yang terputus atau melewati waktu dinilai otomatis saat jendela berakhir.
- **Pengawasan**: monitor admin (status, progres, pelanggaran, mode perangkat), buka kunci, tambah waktu, paksa selesai. Soal terkunci setelah ujian dimulai; koreksi kunci hanya oleh admin dengan alasan + hitung ulang + audit.
- **Kapasitas**: 250 siswa serentak (kriteria uji di ADR-020 dan T-1406).

### 4.15 Masa paralel dan modul kerangka (ADR-021, ADR-022)

- Selama aplikasi lama masih dipakai, **alur uang tetap di aplikasi lama**; modul keuangan, Midtrans, dan dompet di aplikasi ini dibangun tetapi nonaktif sampai tanggal peralihan.
- Setiap modul dapat dinyalakan/dimatikan admin di **Pengaturan > Kunci fitur**. Modul nonaktif tidak tampil di menu.
- Modul **kerangka** (CRUD dasar, isi menyusul): presensi & izin pegawai, jadwal pelajaran, tugas & PR, e-rapor (unggah PDF), buku kas sederhana, asrama.
- Kartu presensi lama (**RFID**) tetap dipakai; QR tetap didukung.

## 5. Persyaratan fungsional (ringkas, ID dipakai di TODO)

- **FR-AUTH**: login dengan username/NIS/no. HP + password, rate limit, reset password oleh admin, ganti password, 2FA opsional untuk admin/bendahara.
- **FR-RBAC**: peran & izin per rute dan per data (policy); menu hanya menampilkan apa yang bisa dibuka.
- **FR-MASTER**: unit, tahun ajaran, rombel, siswa, wali, pegawai, impor Excel siswa+wali.
- **FR-ATT**: kartu QR, scan gerbang, rekap harian, izin, kalender, notifikasi.
- **FR-NOTIF**: kotak masuk, Web Push, preferensi, PWA dapat dipasang.
- **FR-FIN**: jenis tagihan, penerbitan massal, penyesuaian, kasir, kuitansi, laporan piutang.
- **FR-PAY**: Snap, webhook, rekonsiliasi, kedaluwarsa.
- **FR-WALLET**: dompet, top-up, POS, limit, voucher, koreksi.
- **FR-BK**: aturan poin, catatan perilaku, akun poin, ambang & tindak lanjut.
- **FR-REWARD**: penawaran, pengajuan, persetujuan, voucher.
- **FR-HABIT**: item pembiasaan, centang wali, verifikasi guru.
- **FR-QURAN**: rubrik, setoran Qur'an/Hadits/Do'a, materi hadits & doa, target, progres, penghitung kesalahan; PIB.
- **FR-ATTITUDE**: hitung, koreksi, kunci, cetak.
- **FR-REPORT**: laporan & ekspor (PDF/Excel) per modul.
- **FR-AUDIT**: jejak audit semua perubahan data penting.
- **FR-EXAMELIG**: periode ujian, syarat tagihan, kelayakan, dispensasi admin, daftar peringatan ujian kertas, notifikasi wali.
- **FR-CBT**: mapel & penugasan, bank soal, ujian, login siswa NIS+PIN, sesi tahan putus, penilaian + essay, monitor, mode perangkat, ekspor hasil.

## 6. Persyaratan non-fungsional

- **Keamanan**: OWASP ASVS L1 minimum; tidak ada IDOR (wali hanya anaknya); CSRF; rate limit; header keamanan; secret hanya di `.env`; backup terenkripsi harian.
- **Privasi (UU PDP)**: data anak minimum, foto/lampiran di disk privat, akses lampiran lewat URL bertanda tangan berumur pendek, log tanpa data sensitif.
- **Performa**: halaman wali ≤ 2,5 s LCP di HP Android menengah 4G; scan gerbang → umpan balik ≤ 300 ms di server; POS ≤ 500 ms; 1.000 siswa, 2.000 wali, 50 pengguna staf bersamaan tanpa degradasi.
- **Keandalan**: webhook & notifikasi lewat antrean dengan retry; tidak ada uang/poin ganda saat request ganda (idempotensi + transaksi + lock).
- **Aksesibilitas**: keyboard penuh, kontras WCAG AA, label pembaca layar, layar minimal 360 px.
- **Bahasa**: UI Bahasa Indonesia baku sehari-hari; kode dan identifier Bahasa Inggris.
- **Zona waktu**: `Asia/Makassar` (WITA, Kolaka, Sulawesi Tenggara). Semua tampilan jam memakai WITA.
- **Browser**: Chrome/Edge/Firefox/Safari dua versi terakhir; Chrome Android & Safari iOS 16.4+ untuk Web Push.

## 7. Kriteria rilis 1.0

Semua tugas P0 di `TODO.md` selesai dengan bukti · UAT klien lulus per peran · rekonsiliasi Midtrans sandbox 0 selisih pada skenario uji (lunas, kedaluwarsa, webhook ganda, nominal tidak cocok) ·
uji beban scan pagi (300 scan dalam 15 menit) lulus · backup & restore teruji · dokumen serah terima (panduan admin, panduan wali, catatan akses server) diserahkan.

## 8. Pertanyaan terbuka untuk klien

1. Rumus nilai sikap (§4.11) dan ambang predikat.
2. Biaya admin Midtrans ditanggung siapa (§4.5).
3. Keterlambatan otomatis menjadi pelanggaran? (§4.7)
4. Aturan streak mutabaah menghasilkan poin? (§4.9)
5. Daftar aturan poin dan ambang tindakan resmi sekolah (dokumen tata tertib).
6. Daftar reward awal dan nilai voucher.
7. Jam presensi per unit dan apakah sudah ada alat scanner barcode.
8. Logo resmi dan warna identitas yayasan.
9. Apakah nama pencatat catatan perilaku boleh dilihat wali.
10. Data awal: daftar siswa + wali + no. HP dalam Excel; tagihan berjalan yang harus dimigrasikan dari aplikasi lama (saldo awal).
11. ~~Syarat lunas untuk ikut ujian~~ — diputuskan, ADR-016.
12. ~~CBT native~~ — diputuskan, ADR-020.
