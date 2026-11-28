# DECISIONS — Catatan Keputusan Arsitektur (ADR)

Status: **Diterima** (mengikat, jangan dibalik tanpa ADR baru dari pemilik) · **Usulan** (menunggu keputusan) · **Ditolak**.
Agen yang menemukan kebutuhan keputusan baru menulis di bagian _Usulan_ lalu berhenti (AI_RULES §9).

---

## ADR-001 — Laravel 13 + Inertia 3 + React 19 + shadcn/ui (Diterima, 2026-11-26)

**Konteks:** pemilik memakai shadcn/ui dan menginginkan hasil yang rapi dan tidak terlihat "AI slop". Repo PIB memakai Livewire; repo penilaian-tahfidz memakai Inertia.
**Keputusan:** starter kit resmi `laravel/react-starter-kit` (cabang `main`): Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Fortify, Wayfinder.
**Akibat:** komponen shadcn dapat dipakai langsung; logika domain dari PIB (PHP) tetap dapat di-port; UI Livewire PIB tidak dipakai ulang. Satu bahasa frontend (TSX).
**Alternatif ditolak:** Livewire 4 + Blade (shadcn tidak tersedia resmi; menyalin gaya secara manual memperbesar risiko inkonsistensi).

## ADR-002 — Monolit dengan Service + Domain (Diterima)

Satu aplikasi Laravel. Penulisan data hanya lewat `app/Services`; perhitungan murni di `app/Domain`. Tanpa microservice, tanpa API terpisah untuk UI.

## ADR-003 — MySQL/MariaDB di produksi, SQLite untuk tes cepat (Diterima)

Tes default di SQLite in-memory. Tes yang menyangkut lock, konkurensi, dan ledger diberi `->group('mysql')` dan dijalankan terhadap MariaDB lokal di `composer check:mysql` (T-005). Dilarang SQL khusus dialek.

## ADR-004 — Multi-unit dalam satu yayasan, bukan multi-tenant (Diterima)

Tabel `units` (MI, RA, TPQ). Data milik unit difilter oleh scope dan policy. Tidak ada database per sekolah, tidak ada branding per unit.

## ADR-005 — Notifikasi wali lewat Web Push + kotak masuk; WhatsApp disiapkan, tidak aktif (Diterima)

Web Push gratis dan memberikan notifikasi melayang dengan suara. Setiap notifikasi juga disimpan di channel `database` agar tidak hilang. Channel WhatsApp dibuat sebagai antarmuka (`WhatsAppChannel` no-op) agar dapat diaktifkan nanti tanpa mengubah kelas notifikasi.

## ADR-006 — Uang dan poin memakai ledger append-only (Diterima)

`wallet_entries` dan `point_entries` tidak pernah di-update atau dihapus. Koreksi = entri pembalik. Kolom saldo hanyalah cache yang diperbarui di transaksi yang sama (dengan `lockForUpdate`) dan diverifikasi harian. Alasan: aplikasi lama menunjukkan saldo yang tidak konsisten antar-halaman; ledger membuat setiap rupiah dan poin dapat ditelusuri.

## ADR-007 — Midtrans via Laravel HTTP Client, tanpa SDK resmi (Diterima)

SDK `midtrans/midtrans-php` memakai konfigurasi statis global yang sulit dites. Endpoint yang dipakai hanya dua (Snap create, Get Status). `MidtransClient` tipis + `Http::fake()` di tes. Verifikasi signature di `App\Domain\Payments\MidtransSignature` (sudah ada, 11 tes).

## ADR-008 — Kartu QR dengan token acak + scanner barcode sebagai input utama (Diterima)

Token 32 byte acak (bukan NIS) yang disimpan sebagai hash, dapat dicabut. Scanner barcode USB/Bluetooth (bekerja sebagai keyboard) adalah jalur utama karena murah dan cepat; kamera adalah alternatif. Biometrik di luar cakupan.

## ADR-009 — Kelebihan bayar online masuk saldo kantin (Usulan — konfirmasi klien)

Bila tagihan sudah lunas lewat kasir sebelum webhook Midtrans tiba, kelebihan bayar dicatat sebagai saldo kantin siswa dan bendahara diberi tahu. Alternatif: dikembalikan manual oleh bendahara. Default sampai diputuskan: saldo kantin.

**Pembaruan 2026-11-28 (keputusan pemilik atas U-05): dimatikan dulu.** Bawaan `PAYMENTS_OVERPAYMENT_TO_WALLET=false`: kelebihan bayar hanya masuk antrean "Perlu ditinjau" (alasan Kelebihan bayar) dan bendahara diberi tahu; pengembalian diputuskan manual. Menyalakan flag mengaktifkan perilaku saldo kantin di atas tanpa perubahan kode.

## ADR-010 — Pembayaran online hanya pelunasan penuh per tagihan (Diterima)

Wali boleh memilih beberapa tagihan, tetapi tiap tagihan dibayar penuh. Cicilan hanya lewat kasir. Alasan: alokasi parsial online menambah kasus tepi tanpa manfaat besar bagi klien.

## ADR-011 — Notifikasi dikirim setelah commit lewat antrean (Diterima)

`ShouldQueueAfterCommit` untuk semua notifikasi domain. Worker antrean wajib berjalan di produksi (Supervisor, lihat DEPLOYMENT).

## ADR-012 — Zona waktu WITA (Diterima)

Kolaka, Sulawesi Tenggara = `Asia/Makassar`. Semua "hari sekolah" (`school_date`, `log_date`) dihitung dalam WITA. Sudah dikonfigurasi di fondasi.

## ADR-013 — Desain mengikuti standar-ui-project + antislop, font Plus Jakarta Sans (Diterima)

Lihat DESIGN.md. Override terhadap standar global dicatat di DESIGN.md §8 (disalin ke `standar-ui-project/projects/madrasah-terpadu/` di T-007).

## ADR-014 — Registrasi publik dimatikan; akun dibuat oleh sekolah (Diterima)

Akun wali dibuat lewat impor/data induk dengan password awal acak. Tidak ada halaman daftar publik.

## ADR-015 — Wajib ganti password saat login pertama dinonaktifkan sementara (Diterima, 2026-11-26)

**Keputusan pemilik:** fitur paksa ganti password (`must_change_password`) **tidak dibangun** untuk saat ini. Pengguna tetap dapat mengganti password sendiri di halaman Akun, dan admin dapat me-reset password.
**Akibat:** kolom `must_change_password` tidak dibuat; tidak ada middleware atau redirect paksa. Bila diaktifkan kembali nanti: migration baru + ADR baru.
**Agen:** jangan menambahkan kolom, middleware, atau alur paksa ganti password.

## ADR-016 — Syarat ujian: tagihan wajib lunas (Diterima, 2026-11-26; semula U-07)

**Jawaban klien:**

1. Ujian memakai **CBT**. Bila ujian kertas, cukup **peringatan** (bukan blokir sistem).
2. Tagihan yang wajib lunas **ditentukan bendahara/admin** per periode ujian: SPP sampai bulan tertentu, dan boleh ditambah tagihan lain sesuai kebijakan sekolah.
3. Siswa yang belum lunas dapat diberi **dispensasi oleh admin** (hak prerogatif sekolah).
4. Berlaku untuk **MI dulu**; unit lain menyusul tanpa ubah desain.

**Desain:**

- **Periode ujian** (`exam_periods`): unit, nama (mis. "PAS Ganjil"), jenis pelaksanaan `cbt` | `kertas`, tanggal mulai–selesai, status draf/aktif/selesai. Dibuat bendahara/admin.
- **Syarat tagihan** (`exam_period_requirements`): daftar aturan per periode: jenis tagihan + batas periode tagihan (mis. SPP s.d. Nov 2026), atau tagihan sekali tertentu (mis. Kegiatan). Siswa **layak** bila semua tagihan yang cocok dengan syarat berstatus lunas (sisa 0). Cicilan sebagian = belum layak.
- **Dispensasi** (`exam_eligibility_overrides`): hanya **admin**; alasan wajib, berlaku untuk satu periode, dapat dicabut; tercatat lewat `AuditLogger`. Tidak mengubah tagihan atau saldo.
- **Kelayakan dihitung saat dibaca** dari tagihan + dispensasi (tidak disimpan sebagai kolom), sehingga pembayaran Midtrans yang masuk langsung membuat siswa layak tanpa sinkronisasi.
- **CBT:** ujian CBT (ADR-020) terhubung ke periode ujian; gerbang kelayakan dijalankan di Service saat siswa memulai sesi, bukan di browser.
- **Kertas:** tidak ada blokir. Panitia/pengawas mendapat daftar siswa belum layak per rombel (cetak), dan layar daftar menampilkan peringatan; keputusan di ruang ujian tetap pada sekolah.
- **Wali:** kartu "Perlu tindakan" di beranda saat periode aktif dan anak belum layak, berisi tagihan yang harus dilunasi + tombol bayar. Notifikasi `billing` H-7 dan H-1 sebelum tanggal mulai (maks satu per hari).
- **Hak akses:** bendahara & admin membuat periode dan syarat; hanya admin memberi/mencabut dispensasi; wali kelas melihat daftar rombelnya (baca saja).

**Jawaban lanjutan (2026-11-26):** CBT dibangun **native** di aplikasi ini (ADR-020). Karena itu endpoint API dan ekspor untuk CBT luar **tidak dibuat**; kelayakan diperiksa langsung saat siswa masuk ujian CBT (siswa tidak layak melihat pesan netral "Hubungi admin sekolah" tanpa menyebut nominal). Ekspor daftar tetap ada untuk ujian kertas.

**Risiko:** menahan hak ujian karena biaya bisa berbenturan dengan kebijakan Kemenag/dinas setempat dan sering dikeluhkan wali. Yayasan perlu memastikan aturan ini sesuai ketentuan dan tertulis di tata tertib/perjanjian pembayaran. Dispensasi admin menjadi katup pengaman.

**Akibat:** PRD §4.4 + FR-FIN ditambah aturan kelayakan ujian; SCHEMA §D ditambah tiga tabel di atas; TODO Fase 6 ditambah tugas: periode & syarat, perhitungan kelayakan + dispensasi, ekspor/endpoint CBT, kartu & notifikasi wali, daftar peringatan ujian kertas; mockup ditambah layar "Kelayakan ujian" dan kartu beranda wali.

## ADR-017 — Hafalan Qur'an, Hadits, dan Do'a (Diterima, 2026-11-26; semula U-08)

**Permintaan klien:** setoran hafalan punya tiga jenis: Qur'an, Hadits, Do'a. Materi hadits dan doa diinput oleh admin.

**Desain:**

- Qur'an tetap seperti sekarang (rentang surah:ayat, `surahs`, `memorized_ayahs`, ziyadah/murajaah).
- Tabel baru `memorization_materials` (master, dikelola **admin**): `unit_id null`, `kind` (`hadith|prayer`), `title` (mis. "Doa sebelum makan"), `arabic_text text null`, `translation text null`, `source string null` (perawi/kitab untuk hadits), `sort_order`, `is_active`. Pola CRUD biasa; materi yang sudah punya setoran hanya dinonaktifkan, tidak dihapus.
- `memorization_records` ditambah `kind` (`quran|hadith|prayer`) dan `memorization_material_id null`; kolom surah/ayat hanya wajib bila `kind = quran`. Validasi di Domain, bukan di browser.
- `memorization_targets` ditambah `kind`; target hadits/doa berupa daftar materi (`material_ids json`), bukan rentang ayat. Progres = jumlah materi lulus / jumlah materi target.
- Rubrik berversi dipakai untuk ketiga jenis; tiap jenis boleh punya rubrik sendiri (mis. Qur'an: kelancaran, tajwid, makhraj; doa: kelancaran, makhraj).
- Wali melihat progres per jenis (tab Qur'an / Hadits / Do'a di Riwayat > Hafalan).

**Jawaban klien (2026-11-26):** hadits dan doa **tanpa** ziyadah/murajaah; murajaah hanya untuk Qur'an. Jadi `memorization_records.type` hanya wajib bila `kind = quran` dan bernilai null untuk hadits/doa.
**Akibat:** PRD §4.10, SCHEMA (§ setoran), TODO Fase 11 (T-1102, T-1104, T-1105 diperluas + tugas baru master hadits/doa), REUSE_MAP §3 catatan, mockup 09 dan 14 diperbarui.

## ADR-018 — Penghitung kesalahan dengan PageDown/PageUp dan tombol ± (Diterima, 2026-11-26; semula U-09)

**Permintaan klien:** menghitung kesalahan saat setoran/penilaian bisa dengan tombol Page Up/Page Down di laptop, dan tombol tambah/kurang di HP.

**Desain** (komponen `MistakeCounter` di `components/app`, dipakai untuk kriteria rubrik mode `deduction` dan nilai PIB):

- Satu kriteria **aktif** pada satu waktu (ditandai ring `--primary` + teks "aktif"); pindah kriteria dengan Tab/klik atau tombol panah kiri/kanan.
- Di halaman penilaian, **PageDown = tambah 1, PageUp = kurang 1** pada kriteria aktif, tanpa perlu fokus di input. Alasan: tombol "next" pada presenter/clicker nirkabel mengirim PageDown, sehingga guru bisa menambah kesalahan sambil menyimak tanpa melihat layar. Tombol ini tidak berlaku saat fokus di kolom teks (catatan).
- Di HP: tombol − dan + minimal 44 px di kiri-kanan angka; angka besar `tabular-nums`. Tahan untuk ulang tidak dipakai (rawan salah hitung).
- Nilai tidak bisa kurang dari 0 atau melebihi batas kriteria; perubahan diumumkan lewat `aria-live` ("Tajwid: 3 kesalahan") dan getaran pendek di HP bila didukung.
- Nilai akhir dihitung ulang langsung di layar sebagai pratinjau; nilai resmi dihitung ulang di server (`RubricScorer`/`PibScore`).

**Jawaban klien (2026-11-26):** arah tombol disetujui (PageDown = tambah, PageUp = kurang).
**Akibat:** DESIGN.md §4 ditambah `MistakeCounter`; T-1104 dan T-1106 KP ditambah perilaku keyboard + tes; mockup 09 diperbarui.

## ADR-019 — Sholat 5 waktu sebagai item pembiasaan tetap (Diterima, 2026-11-26; semula U-10)

**Permintaan klien:** admin boleh menambah atau mengurangi kegiatan pembiasaan, tetapi sholat 5 waktu harus selalu ada.
**Sudah tercakup:** item pembiasaan sudah berupa master yang dikelola admin (T-1001, `habit_items`). "Mengurangi" = menonaktifkan; item yang sudah punya catatan tidak dihapus agar riwayat dan nilai sikap tetap utuh.
**Tambahan:**

- `habit_items` ditambah `is_locked bool` (default false). Seeder membuat lima item terkunci untuk semua unit: Sholat Subuh, Dzuhur, Ashar, Maghrib, Isya (aspek `spiritual`).
- Item terkunci **tidak bisa dihapus, dinonaktifkan, atau diganti nama/aspeknya**; admin hanya boleh mengubah urutan. Dijaga di Service + Policy (bukan hanya disembunyikan di UI) dan dites.
- Di UI daftar item, item terkunci ditandai ikon gembok + tooltip "Item wajib sekolah"; tombol hapus/nonaktif tidak ditampilkan.
  **Jawaban klien (2026-11-26):** cukup nama waktunya: Subuh, Dzuhur, Ashar, Maghrib, Isya.
  **Akibat:** SCHEMA `habit_items` + kolom `is_locked`; KP T-1001 ditambah aturan item terkunci + tes; mockup 15 (centang wali) menampilkan lima waktu sholat.

## ADR-020 — CBT native di Madrasah Terpadu (Diterima, 2026-11-26; semula U-11)

**Keputusan pemilik:** CBT dibangun di dalam aplikasi ini (bukan aplikasi terpisah), merujuk repo `ilhamrizqiawan21/CBT-Sekolah` (Express + EJS + MySQL, untuk MTs Al-Ihsan) dengan penyempurnaan. **Mengubah PRD §2**: "CBT/ujian online" keluar dari daftar bukan-tujuan; unit pertama **MI**.

**Yang di-port dari CBT-Sekolah (aturan dan pelajarannya, bukan kodenya):**

| CBT-Sekolah                                                                                                      | Di sini                                                     | Catatan                                                                                          |
| ---------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Tipe soal PG, menjodohkan (all-or-nothing), essay tampil-saja dinilai guru dari kertas (D-003, D-006)            | `exam_questions.type` `mcq                                  | matching                                                                                         | essay_paper` | Bobot per tipe **dapat diatur per ujian** (aturan MTs 2/2/4 menjadi bawaan, bukan hardcode) |
| Nilai = total poin / poin maks × 100, dibulatkan (D-005); status `menunggu_essay`                                | `App\Domain\Exam\ExamScorer` (murni, dites)                 | Nilai final setelah essay dinilai                                                                |
| Timer di server (D-007), take-over perangkat dengan `device_token` (D-008)                                       | `ExamSessionService`                                        | Sisa waktu = `deadline − now()` di server; login ulang melanjutkan                               |
| Sinkron jawaban batch idempoten + grace 120 detik (D-009)                                                        | `POST .../answers` batch, unik (`session_id`,`question_id`) | Jawaban disimpan lokal di perangkat lalu dikirim ulang                                           |
| Acak soal/pilihan dengan seed tersimpan (FR-06)                                                                  | `exam_sessions.shuffle_seed`                                | Urutan sama setiap sambung ulang                                                                 |
| Teks Arab RTL + font Arab lokal + kontrol ukuran (FR-07, D-014)                                                  | Komponen `ArabicText`                                       | Font self-hosted (tanpa CDN, sesuai AI_RULES)                                                    |
| Monitor admin: online/selesai/terkunci, progres, pelanggaran; buka kunci, tambah waktu, paksa selesai (FR-11–12) | Halaman monitor                                             | Polling 5 detik dulu; WebSocket (Laravel Reverb) hanya lewat ADR terpisah karena dependensi baru |
| Log pelanggaran (pindah tab, keluar layar penuh, salin-tempel), token ujian opsional (FR-13–14)                  | `exam_violations`, `exams.access_token`                     | Tetap disebut "best-effort"; pengawasan fisik wajib                                              |
| Kunci jawaban tidak pernah dikirim ke klien (NFR-04)                                                             | Resource Inertia khusus siswa                               | Dites                                                                                            |

**Penyempurnaan (dari AUDIT_KESIAPAN §B CBT-Sekolah dan kebutuhan baru):**

1. Sesi `keluar_paksa`/terputus yang tidak ditindaklanjuti **otomatis dinilai** saat jendela ujian berakhir (job terjadwal), tidak hilang dari hasil.
2. Soal **terkunci** setelah ujian dimulai; perubahan setelahnya hanya lewat "koreksi kunci" oleh admin dengan alasan + nilai dihitung ulang + audit.
3. Ekspor Excel menetralkan awalan `= + - @` (formula injection); logout lewat POST; tanpa skrip inline (Inertia/React).
4. Gerbang kelayakan **ADR-016** saat memulai sesi; dispensasi admin langsung berlaku.
5. Data siswa, rombel, mapel guru memakai data induk yang sudah ada (tidak ada impor siswa terpisah).
6. Hasil ujian dapat dilihat wali setelah guru menyelesaikan penilaian (pengaturan per ujian; default tidak tampil, sesuai D-017 CBT-Sekolah).
7. Siswa MI masih kecil: satu soal per layar, tombol pilihan ≥ 48 px, navigasi nomor soal besar, tanda "ragu-ragu", tanpa hitung mundur yang menegangkan (sisa waktu tampil tenang, peringatan hanya 5 menit terakhir).

**Data baru (ringkas):** `subjects` (mapel per unit), `teaching_assignments` (guru × mapel × rombel), `exams` (periode ujian ADR-016 opsional, mapel, rombel/tingkat, jendela waktu, durasi, acak, bobot, token, batas pelanggaran, tampil-ke-wali), `exam_questions` (+ `exam_question_options`), `exam_sessions` (siswa, ujian, mulai, deadline, tambahan waktu, status, `device_token`, `shuffle_seed`), `exam_answers`, `exam_essay_scores`, `exam_results`, `exam_violations`.

**Login siswa (bagian yang butuh keputusan):** aplikasi ini belum punya akun siswa (PRD §3). Usulan: guard terpisah `student` khusus halaman ujian, masuk dengan **NIS + PIN ujian** (PIN di-hash, dibagikan wali kelas, bisa di-reset), sesi hanya berlaku selama jendela ujian; tidak memberi akses ke menu lain. Alternatif: scan **kartu QR presensi** (T-501) + PIN singkat. Rate limit per NIS (D-018 CBT-Sekolah).

**Jawaban pemilik (2026-11-26):**

1. **Ketiga perangkat** (lab komputer, tablet sekolah, HP orang tua) harus didukung lewat pengaturan, tanpa perubahan kode saat cara pelaksanaan berubah.
2. Login siswa: **NIS + PIN**.
3. CBT berjalan di **server yang sama** dengan aplikasi utama.
4. Kapasitas rancangan: **250 siswa serentak**.

**Skenario perangkat** (dipilih admin per ujian lewat `exams.device_mode`; satu ujian boleh mengizinkan lebih dari satu mode, mode sesi tercatat di `exam_sessions.device_type`):

| Mode                                                                                                                                         | Perangkat           | Aturan anti-curang                                                                                                                  | Catatan                                                                |
| -------------------------------------------------------------------------------------------------------------------------------------------- | ------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `lab`                                                                                                                                        | Komputer/laptop lab | Layar penuh wajib; keluar layar penuh/pindah tab dihitung pelanggaran; terkunci setelah batas pelanggaran (default 3), dibuka admin | Bisa dipadukan Safe Exam Browser (opsional, tanpa ketergantungan)      |
| `tablet`                                                                                                                                     | Tablet sekolah      | Layar penuh bila didukung; pindah aplikasi dicatat dan dihitung pelanggaran                                                         | Disarankan mode kios/Guided Access di perangkat; panduan di DEPLOYMENT |
| `phone`                                                                                                                                      | HP orang tua        | Pelanggaran **dicatat saja, tidak mengunci** (notifikasi/telepon masuk sering memicu keluar aplikasi); opsional wajib lewat Exambro | Hemat kuota: paket soal diunduh sekali, jawaban dikirim batch          |
| Semua mode memakai tampilan yang sama (responsif 360 px–1280 px); yang berbeda hanya kebijakan. Pengawas melihat mode tiap siswa di monitor. |

**Skenario 250 siswa serentak** (kriteria terima, masuk T-1406 Uji beban):

- Gelombang masuk: 250 login NIS+PIN dalam 2 menit tanpa galat; rate limit per NIS, bukan per IP (banyak siswa berbagi IP Wi-Fi sekolah).
- Paket soal: satu permintaan per siswa saat mulai (tanpa kunci jawaban, ukuran kecil, aset font di-cache).
- Sinkron jawaban: batch setiap perubahan, paling sering tiap 10 detik per siswa (≈ 25 permintaan/detik); p95 ≤ 500 ms di server target.
- Monitor admin: polling 5 detik, satu kueri agregat (tanpa N+1).
- Penutupan: 250 sesi berakhir bersamaan dinilai lewat antrean dalam ≤ 2 menit.
- Ketahanan: Wi-Fi diputus 60 detik di tengah ujian lalu disambung → tidak ada jawaban hilang; restart server/worker → siswa tidak keluar dan sisa waktu tetap.

**Akibat:** PRD §2, §3 (peran/konteks siswa ujian), §4 bagian baru "Ujian CBT", §5 FR-CBT; SCHEMA bagian baru; ARCHITECTURE (guard `student`, job penutupan sesi); REUSE_MAP §5 (CBT-Sekolah); TODO **Fase 15 — CBT** (setelah Fase 6 karena bergantung pada kelayakan U-07); DESIGN area baru `exam-layout`; mockup: bank soal, buat ujian, monitor, layar siswa ujian, input nilai essay, kelayakan ujian.

## ADR-021 — Masa paralel dengan aplikasi lama: uang tetap di aplikasi lama (Diterima, 2026-11-27; keputusan pemilik dari obrolan dengan klien)

**Konteks:** klien masih akan memakai aplikasi lama (amaljayaberkah.id) untuk sementara. Pembayaran (tagihan, cicilan, Midtrans, saldo e-wallet) di sana sudah berjalan dengan kunci Midtrans produksi.
**Keputusan:**

- Selama **masa paralel**, semua alur uang tetap di aplikasi lama: tagihan, kasir, Midtrans, dan dompet kantin. Modul keuangan, Midtrans, dan dompet di aplikasi ini tetap dibangun sesuai PRD, tetapi **dinonaktifkan lewat kunci fitur** (T-211) sampai tanggal peralihan (PAYMENTS §8). Tidak ada dua aplikasi yang menerbitkan tagihan bersamaan.
- Prioritas pengerjaan bergeser ke modul tanpa uang: data induk, portal wali, notifikasi, presensi, BK, pembiasaan, hafalan, CBT, dan kerangka paritas (ADR-022).
- Kelayakan ujian (ADR-016) membutuhkan tagihan di aplikasi ini, jadi **ditunda sampai peralihan**. Selama masa paralel, CBT berjalan tanpa periode ujian (kolom `exam_period_id` kosong).
- Data induk selama masa paralel: impor dari ekspor Excel aplikasi lama (T-207). Penyelarasan ulang dilakukan manual oleh admin sebelum peralihan; tidak ada sinkronisasi otomatis antar-aplikasi.
  **Akibat:** urutan paket di TODO berubah (P-04, P-05, P-08, P-10, P-15 pindah ke "Rilis peralihan"); jalur A (Claude) mengerjakan CBT lebih dulu.

## ADR-022 — Paritas bertahap: kerangka dulu, isi menyusul, di balik kunci fitur (Diterima, 2026-11-27; menjawab sebagian U-12)

**Konteks:** klien ingin fitur setara aplikasi lama. Untuk fitur U-12 (d)–(i), arahan pemilik: buat **kerangka yang mirip dulu, konten menyusul**.
**Keputusan:**

- **Kerangka** = tabel + model + Service + CRUD dasar memakai kit CRUD (T-112) + menu + empty state + otorisasi + tes otorisasi. **Tanpa** aturan bisnis lanjutan, laporan, atau notifikasi. Tugas bertanda **[KERANGKA]** di TODO.
- Setiap modul (termasuk modul PRD) punya **kunci fitur** di Pengaturan (T-211). Modul nonaktif tidak tampil di menu dan rutenya 404. Kerangka yang belum siap dipakai dinonaktifkan, bukan ditampilkan sebagai halaman kosong ("dead navigation").
- Kerangka yang dibangun: presensi & izin pegawai, jadwal pelajaran, tugas & PR, e-rapor (unggah PDF per siswa), buku kas sederhana, asrama (kamar + penempatan). Field minimal di SCHEMA §L.
- **Diambil penuh** (bukan kerangka): kartu **RFID** sebagai token presensi (ADR-008 diperluas: token kartu boleh UID RFID; QR tetap didukung), diskon & beasiswa berulang per siswa, unggah bukti transfer (keduanya bagian Rilis peralihan), kenaikan kelas & mutasi siswa.
- Tetap bukan tujuan: PPDB, CMS website, perpustakaan, armada, koperasi, helpdesk, payroll, dana BOS, ZIS.

## ADR-023 — Versi minimum PHP 8.4 (Diterima, 2026-11-27; keputusan pemilik)

**Konteks:** `composer.json` menyatakan `php: ^8.3`, tetapi `composer.lock` mengunci Symfony 8.1 (dependensi Laravel 13) yang butuh PHP ≥ 8.4.1. Akibatnya CI job PHP 8.3 gagal di `composer install` sejak awal, dan server PHP 8.3 tidak akan bisa memasang aplikasi.
**Keputusan:** versi minimum PHP = **8.4**. `composer.json` `php: ^8.4`; matriks CI 8.4 dan 8.5; dokumen deploy menyebut PHP-FPM 8.4+. Workflow bawaan starter kit `.github/workflows/tests.yml` dihapus karena menduplikasi `ci.yml` dengan PHP 8.3 dan Node 22.
**Akibat:** server staging/produksi klien wajib PHP 8.4+ (DEPLOYMENT §1). Tidak ada dependensi yang berubah; hanya `content-hash` di `composer.lock`.

## ADR-024 — Fitur bawaan starter kit yang dimatikan: hapus akun sendiri dan passkey (Diterima, 2026-11-27; keputusan pemilik)

**Konteks:** cek UI Fase 1 menemukan dua fitur starter kit yang aktif tanpa pernah diputuskan: (1) setiap pengguna, termasuk wali dan admin terakhir, dapat menghapus akunnya sendiri (`DELETE /settings/profile`) tanpa audit, melewati pengaman T-114; (2) login dan konfirmasi kata sandi dengan passkey, padahal PRD menetapkan login dengan username/nomor HP/NIS (T-103).
**Keputusan:** rute, request, dan komponen hapus akun sendiri dihapus; akun hanya dinonaktifkan oleh admin (T-114, T-206). `Features::passkeys` dimatikan di `config/fortify.php`; tombol dan pengelolaan passkey dihapus dari UI. Tabel `passkeys` dibiarkan (migrasi lama tidak diedit). Dua tes starter "user can delete their account" dihapus dan asersi passkey di `SecurityTest` dibalik, karena tes itu menjamin perilaku yang kini dilarang; penggantinya `tests/Feature/Auth/AccountLockdownTest.php`.
**Akibat:** menyalakan kembali passkey butuh ADR baru (perlu audit login passkey + pemeriksaan `is_active` di jalur itu).

## ADR-025 — Kewajiban 2FA Admin & Bendahara dimatikan sementara (Diterima, 2026-11-27; keputusan pemilik)

**Konteks:** T-104 mewajibkan Admin dan Bendahara mengaktifkan 2FA sebelum memakai aplikasi. Pemilik meminta verifikasi dua langkah dinonaktifkan dulu selama masa pengembangan.
**Keputusan:** kewajiban 2FA dikendalikan sakelar `sekolah.security.require_two_factor` (`REQUIRE_TWO_FACTOR` di `.env`), **bawaan `false`**. Saat `false`, `EnsureTwoFactorForPrivilegedRoles` melewatkan semua pengguna. Fitur 2FA Fortify tetap ada sebagai pilihan: akun yang mengaktifkannya tetap ditanya kode saat masuk. Tes T-104 tetap menguji jalur wajib dengan sakelar dinyalakan.
**Akibat:** sebelum produksi pemilik menyalakan `REQUIRE_TWO_FACTOR=true` (DEPLOYMENT §2), atau mencatat ADR baru bila tetap opsional.

## ADR-027 — Peningkatan identitas visual & kedalaman permukaan Tingkat 2 (Diterima, 2026-11-27; keputusan pemilik)

**Konteks:** Tampilan awal terlalu datar (latar `#FBFAF7`, kartu `#FFFFFF`, dan topbar `#FFFFFF` hampir sama-sama putih tanpa kedalaman) sehingga terasa steril.
**Keputusan:** Terapkan peningkatan visual terpusat di token dan komponen bersama tanpa melanggar antislop: (1) topbar staf memakai `bg-primary text-primary-foreground` dengan indikator unit lembut; (2) latar halaman light dihangatkan ke `oklch(0.96 0.006 85)` dengan `shadow-xs` pada kartu/tabel agar permukaan terangkat rapi; (3) motif geometris Islami SVG lokal halus (`.bg-islamic-pattern` / `.bg-islamic-pattern-subtle`) hanya di titik fokus identitas (login, banner Beranda Staf, kartu identitas anak Beranda Wali); (4) header/hover tabel ber-tint primer tipis, wadah ikon `EmptyState` memakai `bg-primary/10 text-primary`, tombol memakai `active:scale-[0.98]`; (5) Beranda Staf menampilkan ringkasan data nyata yang bisa diklik dan pintasan aksi cepat sesuai izin.
**Akibat:** `DESIGN.md` §2–3 diperbarui; perubahan berlaku serentak di seluruh layar.

## ADR-026 — Fitur 2FA dimatikan sementara (Diterima, 2026-11-27; keputusan pemilik, melengkapi ADR-025)

**Konteks:** setelah ADR-025, kewajiban 2FA sudah mati, tetapi akun yang pernah mengaktifkan 2FA tetap ditanya kode saat login. Pemilik meminta autentikasi dua faktor dinonaktifkan dulu sepenuhnya.
**Keputusan:** sakelar `sekolah.security.two_factor_enabled` (`TWO_FACTOR_ENABLED`, **bawaan `false`**). Saat `false`: pipeline login (`Fortify::authenticateThrough` di `FortifyServiceProvider`, sama dengan pipeline bawaan Fortify) tidak memasang tantangan 2FA; halaman Keamanan tidak menampilkan pengaturan 2FA; `EnsureTwoFactorForPrivilegedRoles` dilewati walau `REQUIRE_TWO_FACTOR=true`, agar admin tidak terkunci. Data 2FA yang tersimpan tidak dihapus. Rute Fortify 2FA tetap terdaftar (dibutuhkan Wayfinder) dan tes jalur 2FA tetap berjalan dengan sakelar dinyalakan.
**Akibat:** sebelum produksi pemilik menyalakan `TWO_FACTOR_ENABLED=true` (dan `REQUIRE_TWO_FACTOR=true` bila tetap wajib untuk Admin/Bendahara), DEPLOYMENT §2.

## ADR-028 — Navigasi staf: sidebar kiri + topbar ramping, katalog menu lengkap dengan label "Segera" (Diterima, 2026-11-27; keputusan pemilik)

**Konteks:** pengamatan menu admin aplikasi lama (±15 grup, ±130 item, REUSE_MAP §4) dan permintaan pemilik agar jumlah menu disempurnakan dulu, isi menyusul satu per satu. Menu topbar (DESIGN §3, mockup 01) hanya muat ±8 grup di 1280 px; katalog baru ±12 grup. Rute sementara `tagihan`/`hafalan`/`laporan` merender Beranda (menu mati).
**Keputusan:**

- Area staf memakai **sidebar kiri berkelompok** (dapat diciutkan menjadi ikon di ≥ 1024 px, diingat per perangkat; sheet kiri di < 1024 px) dan **topbar ramping** (tombol sidebar, breadcrumb, pemilih unit/tahun ajaran, akun). Override atas mockup 01 dan standar 02 (topbar menu), dicatat di DESIGN §3 dan §8.
- Katalog menu = modul yang sudah ada + modul yang direncanakan (TODO) + celah dari pengamatan aplikasi lama. Modul "bukan tujuan" PRD §2 (PPDB, CMS, perpustakaan, armada, koperasi, helpdesk, biometrik, WA API) **tidak** masuk menu.
- Item yang modulnya belum dibangun tampil dengan label **"Segera"** dan membuka halaman jujur "Modul ini sedang disiapkan" (`admin.soon`), bukan Beranda. Item tetap disaring izin; item modul uang tetap mengikuti kunci fitur (ADR-021), item lain tidak.
  **Akibat:** `App\Support\Navigation` menjadi satu-satunya katalog menu (ikon grup, item jadi/segera); `layouts/staff-layout.tsx` ditulis ulang; DESIGN §3/§8 diperbarui. Saat modul selesai, itemnya diganti dari "segera" ke rute nyata pada tugas modul tersebut.

## ADR-029 — Nilai sikap menjadi "penilaian karakter" berbasis kurikulum yang dapat dikonfigurasi (Diterima, 2026-11-27; keputusan pemilik, menutup U-01)

**Konteks:** PRD §4.11 mengusulkan predikat sikap spiritual/sosial A–D (konsep K-13). Rapor madrasah kini mengikuti Kurikulum Merdeka sesuai **KMA 1503 Tahun 2025** (perubahan KMA 450/2024): P5RA diganti kokurikuler yang dinilai di rapor utama, dan rapor resmi dibuat di **RDM** Kemenag. Klien belum punya rumus; pemilik memutuskan: (1) Kurikulum Merdeka bawaan dengan opsi kurikulum nasional lain, (2) komponen dan tingkatan dapat diatur, (3) arsitek memilih aturan kecukupan data, (4) kepala unit boleh mengubah predikat.
**Keputusan:**

- **Profil kurikulum per unit**: `merdeka` (bawaan) atau `k13`. Profil menentukan daftar **dimensi** dan **skala capaian**; keduanya data, bukan kode.
    - Merdeka (bawaan, nama dimensi dan label skala **wajib dicocokkan** dengan panduan kokurikuler KMA 1503 sebelum dipakai): dimensi awal _Keimanan & ketakwaan_, _Kedisiplinan & kemandirian_, _Akhlak sosial & kepedulian_; skala _Sangat Berkembang_ ≥ 90, _Berkembang Sesuai Harapan_ ≥ 75, _Sedang Berkembang_ ≥ 60, _Mulai Berkembang_ < 60.
    - K-13: dimensi _Spiritual_ dan _Sosial_; skala A ≥ 90 (Sangat Baik), B ≥ 80 (Baik), C ≥ 70 (Cukup), D < 70 (Perlu Bimbingan).
- **Komponen** (sumber data, masing-masing dinormalkan ke 0–100) dipasang per dimensi dengan **bobot** (jumlah 100):
    | Sumber                                                                                                                                                                                                                                                                                               | Nilai 0–100                                                                                                | Bawaan tingkatan                            |
    | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
    | Pembiasaan per aspek (spiritual/sosial)                                                                                                                                                                                                                                                              | % centang terverifikasi terhadap hari efektif × item aktif sejak siswa masuk rombel                        | —                                           |
    | Pembiasaan item terpilih (mis. sholat 5 waktu)                                                                                                                                                                                                                                                       | sama, hanya item terpilih                                                                                  | —                                           |
    | Kehadiran tepat waktu                                                                                                                                                                                                                                                                                | hadir tepat waktu ÷ hari efektif (izin/sakit dikeluarkan dari penyebut)                                    | —                                           |
    | Poin pelanggaran semester                                                                                                                                                                                                                                                                            | tingkatan dari jumlah poin catatan aktif                                                                   | 0→100, 1–10→90, 11–25→75, 26–50→60, > 50→40 |
    | Poin kebaikan & prestasi semester                                                                                                                                                                                                                                                                    | tingkatan dari poin **yang diperoleh** (bukan saldo; penukaran reward dan poin streak U-04 tidak dihitung) | 0→70, 1–10→80, 11–25→90, ≥ 26→100           |
    | Hafalan & PIB (setelah P-14)                                                                                                                                                                                                                                                                         | % capaian target semester                                                                                  | —                                           |
    | Observasi guru                                                                                                                                                                                                                                                                                       | wali kelas memilih tingkat 1–4 → 40/60/80/100                                                              | —                                           |
    | Bawaan Merdeka: Keimanan = pembiasaan spiritual 100; Kedisiplinan = kehadiran tepat waktu 50 + pelanggaran 50; Akhlak sosial = pembiasaan sosial 50 + kebaikan 25 + pelanggaran 25. Bawaan K-13: Spiritual = pembiasaan spiritual 100; Sosial = pembiasaan sosial 50 + kebaikan 20 + pelanggaran 30. |
- **Kecukupan data (pilihan arsitek):** komponen pembiasaan dan kehadiran hanya dipakai bila data siswa itu mencakup ≥ 60% hari efektif sejak masuk rombel. Komponen yang kurang dibuang dan bobot sisanya dinormalkan ulang. Bila bobot yang tersisa < 50, dimensi ditandai **"Data kurang"** tanpa capaian hitungan; wali kelas wajib menetapkan capaian dengan alasan. Persentase cakupan selalu tampil di tabel. Alasan: anak tidak boleh dirugikan karena orang tua jarang mencentang, dan wali kelas tetap memegang keputusan.
- **Alur per rombel per semester:** Draf (hitung ulang hanya mengubah nilai hitungan, koreksi wali kelas tetap) → Diajukan & dikunci wali kelas → Disetujui kepala unit. **Kepala unit boleh mengubah capaian** sebelum menyetujui (alasan wajib, audit) dan boleh membuka kunci (alasan wajib, audit). Wali murid melihat setelah disetujui dan menerima notifikasi.
- **Deskripsi** disusun dari templat kalimat per tingkatan per dimensi (dapat diubah admin), mengambil item/komponen terkuat dan terlemah; wali kelas dan kepala unit boleh mengubah.
- **Jejak:** setiap hasil menyimpan angka sumber, cakupan, dan **salinan konfigurasi** yang dipakai; perubahan konfigurasi tidak mengubah nilai yang sudah dikunci.
- **RDM:** aplikasi ini tidak menggantikan RDM. Keluaran disediakan sebagai PDF lampiran dan ekspor Excel per rombel untuk disalin ke RDM (format kolom menunggu contoh dari klien).
  **Akibat:** PRD §4.11, SCHEMA §J, dan TODO Fase 12 ditulis ulang; menu "Nilai sikap" menjadi "Penilaian karakter". Domain `App\Domain\Attitude\CharacterScoreCalculator` murni (tanpa DB) dengan tes tabel; service mengumpulkan input dari `HabitRecapService`, catatan perilaku aktif, dan presensi.

---

## ADR-030 — Akuntansi & Kas: satu Buku Kas Umum append-only sebagai pusat (Diterima, 2026-11-28; arahan pemilik)

**Konteks:** ADR-022 dan U-12 (h) menunda akuntansi (COA, pemasukan non-siswa, buku kas, payroll, BOS, ZIS). Pada 2026-11-28 pemilik mengarahkan agar grup **Akuntansi & Kas** diduplikasi dari aplikasi lama dan **hasilnya lebih baik**, karena keuangan adalah titik berat klien. Struktur aplikasi lama (dibaca hanya-baca): Kategori Anggaran (COA), Pemasukan Non-Siswa, Penggajian, ZIS, Buku Kas Umum, BOS Termin, BOS RKAS, BOS BKU (SPJ K7), Pemetaan Alokasi Kas.

**Keputusan:**

1. **Satu buku besar kas** `cash_entries` per unit, **append-only**: tidak ada ubah/hapus; koreksi = entri pembalik (`reverses_entry_id`) dengan alasan. Semua menu lain (pemasukan non-siswa, belanja BOS, gaji, ZIS, pencairan BOS) **menulis ke buku ini lewat satu service** (`CashLedgerService`), jadi BKU selalu lengkap dan saldo tidak bisa berbeda antarmenu.
2. **Pos akun (COA)** `cash_accounts` per unit: `direction` = `in` (pendapatan) atau `out` (pengeluaran). Pos sistem (Pencairan BOS, Penerimaan ZIS, dll.) dibuat otomatis dan tidak bisa dihapus.
3. **Kotak kas** per entri: `cash` (tunai bendahara) atau `bank` (rekening unit), agar saldo tunai dan bank terlihat terpisah.
4. **Sumber dana** pada pengeluaran: setiap pengeluaran menyebut pos pendapatan yang membiayainya. **Alokasi Dana** (pemetaan) membatasi pos pendapatan mana yang boleh membiayai pos pengeluaran tertentu. Saldo per sumber dana ditampilkan; pengeluaran yang melebihi saldo sumbernya **ditolak**.
5. **BOS**: Termin (pencairan → entri masuk otomatis), RKAS (pagu kegiatan per termin; total pagu ≤ dana termin), BKU BOS/SPJ K7 (belanja per kegiatan → entri keluar otomatis; realisasi ≤ pagu).
6. **ZIS** adalah dana amanah: saldo per kategori ZIS; penyaluran tidak boleh melebihi saldo kategorinya.
7. **Penggajian**: komponen gaji per pegawai per bulan (gaji pokok, tunjangan, honor JP, bonus, potongan absensi, kasbon, potongan lain); pembayaran satuan/massal memposting entri keluar; slip dapat dicetak; pembatalan slip = entri pembalik.
8. Uang integer rupiah; tanggal entri = tanggal bisnis WITA; semua tulis dalam transaksi + `AuditLogger`; izin baru `cash_book.manage` (bendahara, admin) dan `payroll.manage` (bendahara, admin); kunci fitur `cash_book`.
9. Integrasi pembayaran SPP/isi saldo ke BKU dikerjakan sebagai tahap terpisah setelah inti selesai (pemetaan jenis tagihan → pos pendapatan).

**Urutan kerja:** (1) COA + Alokasi Dana + Buku Kas Umum + Pemasukan Non-Siswa; (2) BOS Termin, RKAS, BKU BOS; (3) ZIS; (4) Penggajian; (5) posting otomatis SPP & isi saldo.

**Akibat:** ADR-022 tidak lagi menunda akuntansi, payroll, BOS, dan ZIS. Kerangka `cash_categories` di SCHEMA (T-1605) digantikan `cash_accounts` + `cash_entries` versi lengkap.

## Usulan terbuka (menunggu klien, lihat PRD §8)

- **U-01** Rumus nilai sikap (PRD §4.11). **Ditutup oleh ADR-029** (2026-11-27). Sisa untuk klien: cocokkan nama dimensi & label skala kokurikuler dengan panduan KMA 1503, dan kirim contoh format impor RDM.
- **U-02** Biaya admin Midtrans ditanggung siapa (PRD §4.5). Default: yayasan.
- **U-03** Keterlambatan otomatis menjadi pelanggaran (PRD §4.7). Default: tidak.
- **U-04** Streak mutabaah menghasilkan poin (PRD §4.9). **Diputuskan pemilik (2026-11-27): poin streak mutabaah dapat dikustomisasi oleh admin/user** (aktif/nonaktif, jumlah hari berturut-turut, dan bobot poin kebaikan melalui `SettingKey::HabitStreak*`).
- **U-05** ADR-009 kelebihan bayar. **Diputuskan pemilik (2026-11-28): dimatikan dulu** — kelebihan bayar ditinjau & dikembalikan manual oleh bendahara (lihat ADR-009).
- **U-06** Nama pencatat perilaku terlihat oleh wali. Default: tidak terlihat.
- **U-13** Celah menu dari pengamatan ulang admin aplikasi lama 2026-11-27 (lihat bagian U-13). Sudah tampil di menu sebagai "Segera" (ADR-028).
- **U-14** Pelunasan sisa tagihan `partial` lewat Midtrans. **Diputuskan pemilik (2026-11-28): ikuti PAYMENTS** — tagihan `partial` dilunasi di kasir (opsi 1).
- **U-12** Fitur aplikasi lama yang belum ada di rencana (paritas, REUSE_MAP §4). Sebagian diputuskan lewat ADR-021/ADR-022; yang tersisa: format e-rapor resmi, kebutuhan akuntansi lanjutan, cakupan asrama.

### U-12 — Paritas dengan aplikasi lama (Usulan, 2026-11-27)

**Konteks:** klien meminta "semua fitur sama, bahkan lebih baik". Observasi admin aplikasi lama (REUSE_MAP §4) menemukan fitur **yang dipakai** tetapi tidak ada di PRD; sebagian bertentangan dengan PRD §2 (bukan tujuan).

| #   | Fitur                                                            | Bukti pemakaian            | Usulan arsitek                                                                                                                                 | Perkiraan                        |
| --- | ---------------------------------------------------------------- | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| a   | Kartu **RFID** presensi (bukan QR)                               | 223 kartu aktif            | **Ambil.** UID RFID diterima sebagai token kartu (pembaca RFID USB mengetik UID seperti keyboard); QR tetap opsional; kartu lama dipakai ulang | Kecil: ubah ADR-008, T-501/T-502 |
| b   | Diskon & beasiswa berulang per siswa                             | 754 baris                  | **Ambil.** Master potongan per siswa per jenis tagihan (persen/nominal, masa berlaku), diterapkan otomatis saat penerbitan                     | Sedang, Fase 6                   |
| c   | Unggah bukti transfer oleh wali + verifikasi bendahara           | menu ada                   | **Ambil.** Pelengkap Midtrans                                                                                                                  | Sedang, Fase 6                   |
| d   | Presensi & izin guru/staf                                        | 35 baris                   | Ambil bila klien butuh; gerbang yang sama                                                                                                      | Sedang                           |
| e   | Jadwal pelajaran                                                 | 10 baris                   | Ambil versi sederhana (beranda wali + CBT)                                                                                                     | Kecil–sedang                     |
| f   | Tugas & PR                                                       | 13 baris                   | Tunda (e-learning = bukan tujuan PRD §2)                                                                                                       | Besar                            |
| g   | E-Rapor                                                          | 9 baris                    | Tunda; butuh format rapor resmi                                                                                                                | Besar                            |
| h   | Akuntansi: COA, pemasukan non-siswa, buku kas, payroll, BOS, ZIS | buku kas 1.551, payroll 30 | **Tunda / proyek terpisah**; berisiko tinggi, butuh aturan akuntansi yayasan                                                                   | Sangat besar                     |
| i   | Asrama (kamar, boarding pass)                                    | 143 baris                  | Konfirmasi: dipakai MI/RA/TPQ atau unit pesantren di luar cakupan?                                                                             | Sedang                           |
| j   | Kenaikan kelas, arsip mutasi                                     | 10 baris                   | **Ambil** di data induk (enrollment sudah menyimpan riwayat)                                                                                   | Kecil                            |
| k   | PPDB, CMS, perpustakaan, armada, koperasi, helpdesk              | hampir kosong              | Tetap bukan tujuan                                                                                                                             | —                                |

**Pertanyaan untuk klien:** dari (d)–(i), mana yang **wajib** saat peralihan dan mana yang boleh menyusul? Untuk akuntansi (h), apakah tetap di aplikasi lama/terpisah sementara?

### U-13 — Celah menu dari pengamatan ulang aplikasi lama (Usulan, 2026-11-27)

**Konteks:** pengamatan ulang menu admin `amaljayaberkah.id` (15 grup, ±130 item, hanya membaca) menemukan fitur yang belum ada di PRD/TODO maupun U-12. Atas keputusan pemilik (ADR-028) semuanya sudah tampil di sidebar sebagai item "Segera"; isinya menunggu keputusan di sini.

| #   | Fitur                                                   | Usulan arsitek                                                                                                     |
| --- | ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| a   | Halaman publik Kebijakan Privasi & Kebijakan Pembayaran | **Wajib sebelum P-09b**: Midtrans mensyaratkan keduanya di situs merchant; aplikasi ini belum punya halaman publik |
| b   | Katalog menu/produk kantin                              | Konfirmasi: kasir di sana memilih menu atau mengetik nominal? Menentukan rancangan kasir kantin (P-10)             |
| c   | Ekstrakurikuler + anggota + nilai ekskul                | Ambil bila e-rapor memerlukan nilai ekskul                                                                         |
| d   | Kelulusan & cetak SKL                                   | Ambil, kecil (status lulus sudah ada di mutasi)                                                                    |
| e   | Presensi per mapel (per jam pelajaran)                  | Ambil setelah jadwal pelajaran (T-1602)                                                                            |
| f   | Impor guru & staf dari Excel                            | Ambil, kecil (pola impor siswa T-207)                                                                              |
| g   | Target capaian belajar (TP), supervisi modul ajar       | Tunda; tanyakan pemakaian nyata                                                                                    |
| h   | Inventaris aset, ruangan & peminjaman                   | Tunda; tanyakan pemakaian nyata                                                                                    |
| i   | Undangan rapat, buku tamu, penelusuran alumni           | Tunda; tanyakan pemakaian nyata                                                                                    |
| j   | Leger hasil belajar                                     | Bergantung format e-rapor (U-12 g)                                                                                 |

**Pertanyaan untuk klien:** urutan prioritas (a)–(j), dan apakah (g)–(i) benar dipakai di aplikasi lama.

### U-14 — Sisa tagihan yang sudah dicicil dibayar online (Diputuskan 2026-11-28: opsi 1, ikuti PAYMENTS)

**Temuan.** Mockup 16 "Wali — Tagihan & pembayaran online" menampilkan tagihan berstatus _Sebagian_ (Uang Pangkal, "sisa dari Rp1.250.000") yang bisa dicentang dan dibayar lewat Snap. Sementara `PAYMENTS.md` §3 langkah 1 hanya mengizinkan tagihan `unpaid` ("tagihan `partial` dibayar di kasir").

**Yang diterapkan sekarang (T-702).** Mengikuti `PAYMENTS.md`: tagihan `partial` tidak bisa dicentang; portal wali menjelaskan bahwa tagihan yang sudah dicicil dilunasi di kasir.

**Opsi.**

1. Tetap seperti sekarang (aturan PAYMENTS). Paling sederhana; wali harus ke kasir untuk sisa cicilan.
2. **(Rekomendasi)** Izinkan tagihan `partial` dibayar online sebesar **seluruh sisanya** (bukan cicilan baru). Tetap sesuai ADR-010 (lunas penuh per tagihan); risiko kasir menerima pembayaran di sela waktu sama dengan tagihan `unpaid` dan sudah ditangani alur kelebihan bayar (ADR-009). Perubahan: syarat status di `PaymentOrderService::createForInvoices`, satu tes, dan PAYMENTS §3.

**Dampak bila dipilih opsi 2:** perubahan kecil tanpa migrasi; PAYMENTS §3 dan TODO T-702 diperbarui.
