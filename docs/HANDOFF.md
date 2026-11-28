# HANDOFF — Serah Terima Antar-Sesi Agen

Dokumen hidup. **Dibaca pertama** di awal setiap sesi agen (Claude, Gemini, Codex, ...) dan **diperbarui terakhir** sebelum sesi berakhir.
Isinya hanya keadaan kerja yang tidak tercatat di tempat lain. Rincian tugas tetap di `docs/TODO.md`, keputusan di `docs/DECISIONS.md`, dan riwayat di `git log`.

## Cara memakai

**Awal sesi**

1. Baca bagian "Keadaan sekarang" dan "Langkah berikutnya" di bawah.
2. Cocokkan dengan kenyataan: `git status`, `git log --oneline -10`, dan baris tugas di `docs/TODO.md`. Bila berbeda, **kenyataan menang**; perbaiki dokumen ini.
3. Lanjutkan dari "Langkah berikutnya". Jangan mengulang pekerjaan yang sudah ber-commit.

**Akhir sesi** (atau saat konteks hampir penuh, atau setelah setiap tugas selesai)

1. Perbarui "Keadaan sekarang", "Sedang dikerjakan", "Langkah berikutnya", dan "Temuan terbuka".
2. Tulis tanggal + agen di "Terakhir diperbarui".
3. Commit bersama tugas terakhir, atau sendiri: `docs(handoff): update session state`.

Aturan: singkat dan faktual. Jangan menyalin isi TODO/ADR; tautkan saja. Hapus butir yang sudah selesai.

---

## Terakhir diperbarui

2026-11-28 · Gemini — Duplikasi alur aplikasi lama: **E-Learning > Tugas & PR** (`T-1603`), **E-Learning > E-Rapor** (`T-1604`), dan **Absensi > Presensi & Izin Pegawai** (`T-1601`) selesai. Sebelumnya: grup **Pengguna** dan **Akademik** selesai oleh Claude (lihat bagian "Duplikasi alur aplikasi lama"). Fase 7 Midtrans tetap seperti tercatat di bawah (T-708 dilewati).

## Keadaan sekarang

- **Fase 0 & 1**: semua tugas `[x]`, plus perbaikan temuan cek UI (ADR-024, commit `1cfd189`): hapus akun sendiri dihapus, passkey dimatikan, UI auth/2FA berbahasa Indonesia, tautan & logo Laravel dibuang.
- **2FA dimatikan seluruhnya** (ADR-025 + ADR-026, commit `b17e2c7`): `REQUIRE_TWO_FACTOR` dan `TWO_FACTOR_ENABLED` bawaan `false` — tanpa tantangan kode saat login dan tanpa menu 2FA. Nyalakan keduanya sebelum produksi (DEPLOYMENT §2).
- **Tema dikunci ke mode terang (light-only, commit `fe67ce8`)**: `HandleAppearance`, `app.blade.php`, `use-appearance.tsx`, dan `SettingsLayout` tidak lagi mengaktifkan mode gelap.
- **Fase 2 (Data induk) SELESAI SELURUHNYA (T-201 s.d. T-212)**: T-201 ✔ (`dce871b`), T-202 ✔ (`f48e553`), T-203 ✔ (`2ee9ff7`), T-204 ✔ (`7c001c4`), T-211 ✔ (`f9f1a96`), T-205 ✔ (`d34f185`), T-206 ✔ (`805bffb`), T-207 ✔ (`1acc6cc`), T-212 ✔ (`066f693`), T-210 ✔ (`b1e8422`), T-208 ✔ (`4334a1c`), T-209 ✔ (`7893df5`).
- **Peningkatan visual Tingkat 2 (ADR-027, commit `1c5e693`)**: topbar `--primary` hijau madrasah, latar hangat `oklch(0.96 0.006 85)` + `shadow-xs`, motif geometris Islami SVG lokal halus, tabel & tombol interaktif, serta Beranda Staf interaktif dengan data nyata.
- **Fase 3 & 4 (Portal wali, PWA, Notifikasi) SELESAI SELURUHNYA**: T-301 ✔ (`eac3672`), T-302 ✔ (`f5727e3`), T-303 ✔ (`ddd01b4`), T-304 ✔ (`ef8ef1b`), T-305 ✔ (`78f4f3d`), T-401 ✔ (`c1a3744`), T-402 ✔ (`43e0d43`), T-403 ✔ (`e8beee1`), T-404 ✔ (`f3f259e`), T-405 ✔ (`5ac2cce`).
- **Paket P-07 — Fase 5 (Presensi gerbang + RFID) SELESAI SELURUHNYA**: T-501 ✔ (`bd611b4`), T-502 ✔ (`7c2fb93`), T-503 ✔ (`417469c`), T-504 ✔ (`2f351d2`), T-505 ✔ (`4398cab`), T-506 ✔ (`65babf7`), T-507 ✔ (`9ac58ea`), T-508 ✔ (`68fde07`), T-509 ✔ (portal presensi wali).
- **Paket P-16 — Fase 15 (CBT inti) SELESAI SELURUHNYA**: T-1501 ✔ (`0ca124c`), T-1502 ✔ (`1c6392d`), T-1503 ✔ (`34d7f89`), T-1504 ✔ (`5fdde57`), T-1505 ✔ (`6d8250e`), T-1506 ✔ (`4cdd1e3`).
- **Paket P-17 — Fase 15 (CBT pengawasan & hasil) SELESAI SELURUHNYA**: T-1507 ✔ (`8c521f9`), T-1508 ✔ (`f9cfd61`), T-1509 ✔ (`2e77412`), T-1510 ✔ (`b9353ec`), T-1511 ✔ (`docs/handover/ujian-cbt.md` panduan hari-H CBT).
- **Perbaikan setelah review** (`855c151`): `schoolContext` hanya dibagikan ke peran area `/admin` (`Role::adminArea()`), cache pengaturan TTL 10 menit + ditulis ulang setelah commit, pembersihan logo/favicon memakai nilai lama dari baris terkunci, tahun ajaran terpilih di luar 6 terbaru tetap dipakai, `activate` mengunci semua tahun ajaran urut id.
- **Keputusan pemilik diterapkan** (`1e223e7`): `/` dialihkan ke `dashboard` (tamu → login, pengguna → beranda sesuai peran), halaman `welcome` dihapus; semua input jam memakai `TimeInput` 24 jam `HH.MM`.
- `composer check` hijau setelah T-509. CI GitHub hijau sejak ADR-023 (PHP ≥ 8.4); commit setelah `59a0ff1` belum di-push.
- Lingkungan lokal: `.env` memakai **MariaDB** `madrasah_terpadu` (sudah `migrate` sampai `leave_requests` + `ProductionSeeder`). Tes tetap SQLite in-memory (`phpunit.xml` memakai `memory_limit=512M`).
- Akun uji lokal: username `admin.uji` (Admin, 2FA dimatikan 2026-11-27). Kata sandi dan rahasia TOTP **tidak** dicatat di repo; buat ulang lewat resep "Cek UI lokal".

## Sesi Claude 2026-11-27 (beranda, navigasi, Fase 12, polesan CRUD) — semua sudah di-push

- **Beranda per peran** (`28a3dfe`, `92de690`, `4238b7b`): staf = presensi hari ini + tren 7 hari, izin, ujian, data perlu dilengkapi, mutasi; admin juga perbandingan unit, status sistem, keamanan akun, dan baris "Keuangan & layanan yayasan" (susunan dasbor lama, isi jujur: modul uang "masih di aplikasi lama"; tautan opsional `LEGACY_FINANCE_URL`). Wali = kartu Hari ini (mockup 19), izin, ujian, buku saku. Angka keuangan nyata menyusul di **T-617**.
- **Navigasi staf (ADR-028)**: sidebar kiri berkelompok (akordeon, animasi, mode ikon, sheet ponsel) + topbar ramping (hamburger, breadcrumb, lonceng "Perlu tindakan", pemilih unit/tahun, pita tahun ajaran lama). Katalog menu tunggal di `App\Support\Navigation`; modul belum jadi = label "Segera" → `admin.soon`. **Saat membangun modul, ganti item "Segera"-nya** (tes `ComingSoonTest` menolak label ganda).
- **Fase 12 Penilaian karakter (ADR-029) T-1201–T-1204 selesai** — lihat TODO untuk sisa: `check:mysql` belum dijalankan, komponen hafalan belum tersambung, kolom ekspor RDM menunggu contoh klien, nama dimensi/skala Merdeka wajib dicocokkan KMA 1503.
- **Polesan CRUD** (`1539563`, `362e9d0`, `712b778`): halaman galat Indonesia (`pages/error.tsx`, `bootstrap/app.php`), `FormDialog noValidate`, `DateInput` dd/mm/yyyy (ganti semua `type="date"`, jangan pakai lagi), kolom `actions` `DataTable` lengket kanan, **Profil siswa** `admin.students.show` (mockup 03; tombol Cetak kartu belum), `StudentSearch` di Izin/Tindak lanjut/Reward, jumlah siswa per rombel, bar komposisi di Rekap presensi.
- **Deploy**: setiap rilis jalankan `php artisan migrate --force` **dan** `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (izin baru tidak otomatis masuk peran; tanpa ini admin 403).
- **Data pratinjau lokal** (MariaDB, bukan seeder): user `pratinjau.admin` / `pratinjau.guru` / `pratinjau.wali` (sandi lokal `pratinjau-lokal-123`), rombel MI id > 12, siswa id > 65, ujian/aturan poin berawalan "Pratinjau", konfigurasi karakter unit MI. Hapus dengan skrip `preview_cleanup.php` di scratchpad sesi Claude, atau minta Claude.
- Belum dicek: tampilan ponsel 360 px (browser otomasi tidak bisa < ±700 px), halaman wali penilaian karakter & isi PDF, panel Ujian beranda.
- Sisa usulan polesan: strip ringkasan yang bisa diklik di atas daftar utama, aksi massal (pindah rombel, cetak kartu terpilih, setujui izin), Cetak kartu di profil siswa, filter status yang tidak menghapus kata kunci cari.

## Duplikasi alur aplikasi lama (2026-11-28)

Pemilik memandu menu demi menu; nama menu dibuat ramah. Yang disamakan **alur**, tampilan dipoles nanti.

- **Aplikasi lama (amaljayaberkah.id) = produksi, HANYA BACA.** Buka lewat tautan sidebar, baca teks/atribut DOM saja; jangan klik tombol aksi baris. 2026-11-28 tombol "Ubah Status" di Kelulusan langsung membalik status satu siswa tanpa konfirmasi; sudah dikembalikan ke "Lulus" dan dicek ulang.
- Selesai: sidebar 15 grup, Dasbor, grup Pengguna (Peran & Hak Akses, Guru & Staf, Undangan Rapat, Siswa, Orang Tua, Mutasi, Alumni), perbaikan zona waktu WITA.
- **Akademik** (semua di `routes/academic.php`): Gedung, Ruangan & Lab, Tingkat Kelas/Rombel/Jurusan (master), Kelas & Wali Kelas (jurusan+kuota), Jam Pelajaran & Jadwal mingguan (cek bentrok guru, kelas paralel), Ekstrakurikuler + Anggota & Nilai (presensi, predikat A–D), Tujuan Pembelajaran (per mapel + **tingkat**, bukan rombel — perlu konfirmasi pemilik), Kelulusan & SKL (konfirmasi sebelum ubah status, nomor SKL `001/SKL/MI/2026`, kota dari Pengaturan Identitas), Supervisi Modul Ajar (unggah PDF/DOCX → review/revisi), Peminjaman Ruangan (cek jam beririsan).
- Izin baru: `teaching_modules.upload` (guru), `teaching_modules.review` (kepala unit), `rooms.book` (guru, kepala unit). Item menu boleh berisi beberapa izin dipisah `|` (cukup salah satu).
- Migrasi baru `2026_11_28_000015`–`000028`. Deploy: `migrate --force` + `db:seed --class=RolesAndPermissionsSeeder --force`.
- Data dummy lokal: `LegacyMenusDemoSeeder` (menolak jalan di produksi). Kota sekolah lokal belum diisi.
- Pola: halaman master sederhana pakai `MasterCrudPage` (field `text|number|time|select|textarea`); layout halaman Inertia wajib fungsi panah (`Page.layout = (page) => page`).
- PPDB dilewati atas arahan pemilik. `app/Services/Quran/MemorizationProgressService.php` punya perubahan Pint yang belum di-commit (bukan dari tugas ini).
- **Sesi Gemini 2026-11-28 (belum di-commit, sudah di-stage)**:
  - **E-Learning > Tugas & PR (`T-1603`)**: migrasi `000030`, `Assignment`, `AssignmentPolicy`, `AssignmentService`, `Admin\AssignmentController` + `Guardian\AssignmentController` (baca saja, 404 anak lain), `routes/elearning.php`, halaman `admin/elearning/assignments.tsx` & `guardian/assignments.tsx`, tes `AssignmentTest.php`.
  - **E-Learning > E-Rapor (`T-1604`)**: migrasi `000031`, `ReportCard`, `ReportCardPolicy` (admin/wali kelas), `ReportCardService`, `Admin\ReportCardController` + `Guardian\ReportCardController` (unduh via URL bertanda tangan sementara 15 menit + middleware `signed`), halaman `admin/elearning/report-cards.tsx` & `guardian/report-cards.tsx`, tes `ReportCardTest.php`.
  - **Absensi > Presensi & Izin Pegawai (`T-1601`)**: migrasi `000032`, `StaffAttendance` & `StaffLeaveRequest`, `StaffAttendanceService`, `StaffAttendanceController` + `StaffLeaveRequestController` (keputusan oleh `admin`/`kepala_unit`, persetujuan otomatis mengisi `staff_attendances`), `routes/staff-attendance.php`, halaman `admin/attendance/staff.tsx` & `admin/attendance/staff-leaves.tsx`, tes `StaffAttendanceTest.php`. Catatan: integrasi kartu gerbang pegawai (`GateScanController`) belum dihubungkan.
  - **Belum dilakukan**: `git commit` tertahan bug hook `ecc_PreToolUse` (seluruh berkas `T-1603`, `T-1604`, `T-1601` sudah di-stage di index git, siap di-commit manual); cek langsung di browser belum dilakukan.
  - **Langkah berikutnya**: lanjut **Jadwal Pelajaran (`T-1602`)** / sisa menu **E-Learning** (`Bank Soal`, `Pantau Ujian`, `Hasil Ujian`) & **Absensi**.

## Keuangan, Kantin, Akuntansi & Kas — milik Claude (2026-11-28)

Pembagian kerja (playbook `docs/LEGACY_DUPLICATION.md`): **Claude = Keuangan Siswa, Pembayaran online, Kantin, Akuntansi & Kas**; Gemini = grup menu lainnya. Arahan pemilik: modul uang adalah titik berat klien, hasilnya harus **lebih baik** dari aplikasi lama (bukan sekadar meniru).

**Kantin (selesai):**
- Kategori Menu + Daftar Menu (`3c4c8c6`): kategori dipakai bersama Koperasi (kolom `module`), menu punya harga, stok porsi opsional, status dijual/disembunyikan.
- Kasir Kantin berbasis menu (`05fc4a4`): keranjang → tempel kartu → konfirmasi (saldo, voucher, sisa limit, pembagian bayar) → struk 58 mm. Total dihitung server, stok dikunci & dikurangi dalam transaksi yang sama, klik ganda aman, koreksi mengembalikan stok, notifikasi wali menyebut menu yang dibeli.
- Riwayat Isi Saldo (`7cfce87`): periode, unit, rombel, metode; setor tunai; batal setoran tunai (alasan wajib, sekali, tidak boleh membuat saldo minus, wali diberi tahu); ekspor PDF/Excel.
- Perbaikan halaman Saldo Siswa putih (`9d29747`): paginasi dikirim `{data, meta}`.

**Akuntansi & Kas — Tahap 1 selesai (`95b44ed`, ADR-030 `419cba6`):**
- Satu buku besar `cash_entries` append-only per unit; koreksi = entri pembalik (`reverses_entry_id`, alasan). Semua tulis lewat `App\Services\Accounting\CashLedgerService`.
- Kategori Akun (COA, `cash_accounts` in/out), Alokasi Dana (`cash_account_allocations`), Buku Kas Umum (saldo awal/akhir, saldo berjalan, saldo per sumber dana, tunai/bank, PDF/Excel), Pemasukan Lain.
- Pengeluaran wajib menyebut sumber dana; ditolak bila melebihi saldo sumbernya atau dilarang Alokasi Dana. Pemasukan yang dananya sudah terpakai tidak bisa dikoreksi.
- Izin baru `cash_book.manage`, `payroll.manage` (bendahara + admin). Kunci fitur `cash_book` (lokal sudah dinyalakan). `App\Support\PeriodRange` + komponen `PeriodFilter` = pilihan periode bersama halaman keuangan.
- Migrasi Claude memakai nomor `2026_11_28_1000NN` (Gemini `0000NN`).

**Berikutnya (menunggu arahan pemilik, berhenti di sini atas permintaan 2026-11-28):**
1. BOS: Termin (pencairan → entri masuk otomatis), RKAS (pagu kegiatan ≤ dana termin), BKU BOS / SPJ K7 (belanja per kegiatan, realisasi ≤ pagu).
2. ZIS: penerimaan (muzakki: siswa/wali/pegawai/umum; kategori infaq/shodaqoh/zakat mal/zakat fitrah) dan penyaluran (mustahik), saldo per kategori tidak boleh minus.
3. Penggajian: komponen gaji per pegawai per bulan, bayar satuan/massal → BKU, slip A4, batal slip = pembalik.
4. Posting otomatis pembayaran SPP & isi saldo ke Buku Kas Umum.
5. Kartu SPP (Keuangan Siswa) masih "Segera".

**Belum dicek di browser:** simpan form Pemasukan Lain & Catat Pengeluaran (logika sudah dites). Struktur halaman aplikasi lama untuk BOS/ZIS/Penggajian sudah dibaca (hanya-baca) — rinciannya di ADR-030.

## Sedang dikerjakan

Menunggu arahan grup menu berikutnya dari pemilik (urutan sidebar setelah Akademik: E-Learning).

## Langkah berikutnya

1. **Poles UI (fase terpisah, arahan pemilik 2026-11-28)**: antislop + cek 360/768 px untuk halaman pembayaran, antrean Pembayaran online, dan form isi saldo. Fungsi sudah dicek di desktop dengan DB SQLite terpisah (MariaDB lokal tidak disentuh).
2. **T-708 uji sandbox**: pemilik mengisi `MIDTRANS_SERVER_KEY`/`MIDTRANS_CLIENT_KEY` sandbox di `.env` + tunnel (`cloudflared tunnel --url http://localhost:8000`), `APP_URL` = URL tunnel (header `X-Override-Notification` memakai `APP_URL`). Verifikasi khusus: header override berlaku untuk Snap (PAYMENTS §2a).
3. `composer check:mysql` dengan user DB khusus (tes grup `mysql` baru: `PaymentOrderTest`, `MidtransWebhookTest`).
4. Diputuskan pemilik 2026-11-28: **U-14** ikuti PAYMENTS (tagihan `partial` di kasir); **U-05** kelebihan bayar ke saldo kantin dimatikan dulu (`PAYMENTS_OVERPAYMENT_TO_WALLET=false`). **T-708 dilewati dulu** atas arahan pemilik.
5. Deploy: migrasi `2026_11_28_000013`–`000014`; worker sudah mendengar antrean `payments`; jadwal baru `ExpirePaymentOrders` (15 menit) dan `payments:reconcile` (01.00 WITA).

## Arahan pemilik yang berlaku

- **Berikutnya (2026-11-28): duplikasi FITUR & ALUR dari web asli (aplikasi lama) sama persis** — permintaan klien. Yang disamakan alurnya, bukan tampilannya; tampilan dipoles terpisah (antislop, modern). Butuh dari pemilik: akses/URL aplikasi lama atau rekaman/tangkapan layar per alur, dan urutan modul.

- **Desain wajib sesuai mockup** di `docs/mockup/menu.html` (SVG per layar, mis. "12 · Pola pengaturan"). Sebelum membangun layar, render mockup terkait:
  ekstrak `<svg aria-label="...">` dari `menu.html`, lalu `magick -density 96 file.svg file.png` di direktori scratchpad. Bila mockup bertentangan dengan keamanan (mis. logo SVG), ikuti keamanan dan sebutkan di laporan.
- Pemilik memantau progres lewat `docs/TODO.md`: centang + bukti segera setelah tiap tugas.
- Deadline klien ketat: kerjakan berurutan, satu tugas satu commit, jangan menunggu laporan akhir paket untuk commit.

## Keputusan T-204 yang perlu dikonfirmasi pemilik

1. Unit dan tahun ajaran rombel tetap setelah dibuat (tidak bisa diubah).
2. Salin rombel per unit, hanya rombel aktif; wali kelas ikut disalin hanya bila masih guru aktif.
3. Tingkat (`grade`) teks bebas ≤ 10 karakter.
4. Seorang guru boleh menjadi wali kelas beberapa rombel.
5. Saringan hanya menampilkan unit aktif.

## Keputusan T-205 yang perlu dikonfirmasi pemilik

1. Kolom Rombel mengikuti tahun ajaran di topbar, tetapi form hanya mengatur rombel tahun ajaran aktif.
2. Unit siswa tetap setelah dibuat.
3. Rombel tidak bisa dikosongkan lagi setelah terisi di tahun ajaran aktif.
4. NISN tepat 10 digit; NIS huruf/angka/`.-/` ≤ 20 karakter.
5. Nonaktifkan = status `inactive`; saringan bawaan menampilkan siswa aktif.
6. Audit siswa tidak menyimpan alamat dan jalur foto.

## Keputusan T-206 yang perlu dikonfirmasi pemilik

1. Nomor HP sudah dipakai akun wali → anak ditautkan ke wali itu (nama/hubungan yang diketik diabaikan, tanpa kata sandi baru). Dipakai akun staf → akun itu diberi peran `wali`, tetap memakai username & kata sandinya; reset kata sandi dan nonaktifkan akun bersama hanya lewat Pengguna staf.
2. Surat akun wali dicetak dari browser (A5, `window.print`), bukan PDF server; kata sandi hanya ada di dialog sekali tampil, cetak ulang = reset kata sandi.
3. Hubungan (`ayah|ibu|wali`) satu per wali, disalin ke setiap baris `guardian_student`.
4. Wali wajib terhubung minimal 1, maksimal 10 anak. Anak baru tanpa tanda "Wali utama" otomatis menjadi utama bila siswa belum punya wali utama; mencabut tanda utama menyerahkannya ke wali lain tertua.
5. Saringan unit = wali yang punya anak di unit itu (bawaan unit topbar); status bawaan "Akun aktif". Pemilih anak mencari siswa aktif semua unit.
6. Foto anak untuk wali lewat `/wali/anak/{student}/foto` (`StudentPolicy::view`, wali lain 404); rute staf tidak berubah.
7. Migration memakai timestamp `2026_11_27_100004/100005` (bukan `000005/000006`) agar berjalan setelah `students`.

## Keputusan T-211 yang perlu dikonfirmasi pemilik

1. `dormitory` (asrama) bawaan nonaktif.
2. `exam_eligibility` dikelompokkan bersama modul uang (bawaan nonaktif).
3. Rute kiosk `gerbang`/`kantin` belum memakai middleware `feature:` (area per peran). Perlu keputusan: dikunci per fitur atau tidak.

## Temuan terbuka (belum ada tugasnya)

- Sisa starter yang tidak terpakai: `components/app-header.tsx` + `layouts/app/app-header-layout.tsx` (tidak dirender di mana pun).
- Halaman **Pengguna staf** belum memakai `SettingsShell`, padahal ada di sub-navigasi Pengaturan (mockup menaruhnya di sana sebagai "Pengguna & peran").
- Topbar staf masih memakai inisial "MT", belum logo unggahan (masuk sisa T-208).
- `npx shadcn@latest add` gagal karena mencoba `pnpm`; proyek memakai npm. Komponen `textarea` belum ada.
- `composer check:mysql` lokal: `root` MariaDB memakai `unix_socket`. Jalankan dengan `DB_USERNAME`/`DB_PASSWORD` user khusus ber-DB `madrasah_terpadu_testing`.

## Cek UI lokal (resep singkat)

```bash
npm run build && php artisan serve --host=127.0.0.1 --port=8000
# Hanya bila TWO_FACTOR_ENABLED=true: admin uji dengan 2FA yang bisa dipakai (rahasia TOTP valid):
php artisan tinker --execute '$g=app(PragmaRX\Google2FA\Google2FA::class); $u=App\Models\User::where("username","admin.uji")->first(); $s=$g->generateSecretKey(); $u->forceFill(["two_factor_secret"=>encrypt($s),"password"=>"<kata-sandi-lokal>"])->save(); echo $g->getCurrentOtp($s);'
```

Periksa 1280 / 768 / 360 px, terang & gelap, keyboard saja, empat keadaan, lalu jalankan skill `antislop-ui`. Bersihkan data uji yang diketik ke DB lokal sebelum selesai.
