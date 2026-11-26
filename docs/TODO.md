# TODO — Rencana Eksekusi

Aturan: kerjakan **berurutan per fase, satu tugas per kali** (`AI_RULES.md` §2). Centang `[x]` hanya bila semua KP terbukti dan `composer check` hijau, lalu tulis bukti di baris tugas.
Format tugas: **Ref** = dokumen/kode acuan · **Berkas** = tempat kode · **KP** = kriteria penerimaan · **Tes** = tes minimum.
Prioritas: **P0** wajib rilis 1.0 · **P1** sebaiknya ada di 1.0 · **P2** setelah 1.0. Tanda `[mysql]` = wajib lulus juga di `composer check:mysql`. Tanda `[UI]` = wajib gerbang UI (`DESIGN.md` §9 + antislop).
Tugas yang bergantung pada keputusan **[TERBUKA]** (`DECISIONS.md` U-xx) tidak boleh dimulai sebelum keputusan dicatat.

Ringkasan fase: 0 Fondasi · 1 Cangkang & keamanan · 2 Data induk · 3 Portal wali & PWA · 4 Notifikasi · 5 Presensi · 6 Keuangan · 7 Midtrans · 8 Dompet kantin ·
9 Buku Saku BK & reward · 10 Pembiasaan · 11 Hafalan & PIB · 12 Nilai sikap · 13 Laporan · 14 Mutu, deploy, serah terima · 15 Ujian CBT.

---

## Fase 0 — Fondasi

Selesai oleh arsitek (Claude), 2026-11-26:

- [x] Starter kit `laravel/react-starter-kit` (main): Laravel 13, Inertia 3, React 19, TS, Tailwind 4, shadcn/ui, Fortify, Wayfinder, Larastan 7, Pint, Vite+.
- [x] Pest 4 + `tests/Pest.php`; `spatie/laravel-permission` + migration dipublish.
- [x] `config/app.php`: zona waktu `Asia/Makassar`, locale `id`, faker `id_ID`. `.env.example` berisi variabel Midtrans & VAPID.
- [x] `Model::shouldBeStrict()` di luar produksi (`AppServiceProvider`).
- [x] `config/sekolah.php` (default presensi, dompet, pembayaran, notifikasi); `config/services.php` kunci `midtrans`.
- [x] `App\Enums\Role` (8 peran) + tes; `App\Domain\Payments\MidtransSignature` + 11 tes.
- [x] `composer check` (Pint, Larastan, Pest, tsc, vp check) dan `composer fix`. Bukti: 68 tes lulus.
- [x] Dokumen: PRD, ARCHITECTURE, SCHEMA, DESIGN, PAYMENTS, DECISIONS, AI_RULES, REUSE_MAP, DEPLOYMENT, TODO, GEMINI.md, AGENTS.md, README.md.

Sisa Fase 0 (eksekutor):

- [x] **T-001 Commit awal & remote** (P0) — **Dikerjakan pemilik, bukan agen.** Usulan pesan: `chore: scaffold project foundation`. Periksa dulu `git status` tidak memuat `.env`, sqlite, atau build; remote GitHub privat; branch `main` terlindungi bila memungkinkan.
- [x] **T-002 `lang/id`** (P0) — Berkas: `lang/id/{validation,auth,passwords,pagination}.php` (`php artisan lang:publish` lalu terjemahkan). KP: pesan validasi & auth Fortify tampil Bahasa Indonesia; atribut umum (`name`→`nama`, `phone`→`nomor HP`, ...). Tes: login salah → pesan Indonesia. Bukti: `tests/Feature/LocaleTest.php` + `composer check` ✔ (71 tes, 2026-11-26).
- [x] **T-003 Tes starter kit ke Pest** (P1) — Berkas: `tests/Feature/{Auth,Settings}`. KP: tes PHPUnit kelas bawaan diubah ke sintaks Pest tanpa mengubah perilaku yang dites; jumlah tes tidak berkurang. Tes: suite hijau. Bukti: `composer check` (Pint, Larastan 0, Pest 71 tes/191 asersi, tsc ✔; `vp check` gagal hanya pada berkas tak terlacak `docs/mockup/komponen.html`, 2026-11-26).
- [x] **T-004 Pengecekan arsitektur** (P1) — Berkas: `tests/Unit/ArchTest.php`. KP: Pest arch: tidak ada `dd/dump/ray`; `App\Domain` tidak bergantung pada `Illuminate\Http` atau `Illuminate\Support\Facades\DB`; Controller tidak memakai `DB::`; Model tidak bergantung pada `App\Services`; strict types di `app/`. Bukti: `tests/Unit/ArchTest.php` (5 tes) + Pint/Larastan/Pest (76 tes)/tsc ✔ (2026-11-26).
- [ ] **T-005 Tes MariaDB** (P0) — Berkas: `phpunit.mysql.xml`, skrip composer `check:mysql`. KP: tes bergrup `mysql` jalan terhadap DB `madrasah_terpadu_testing` (MariaDB lokal), guard menolak berjalan bila nama DB tidak berakhiran `_testing`. Tes: satu tes `lockForUpdate` sederhana lulus.
- [x] **T-006 CI** (P1) — Berkas: `.github/workflows/ci.yml`. KP: PHP 8.3 & 8.5, `composer install`, `npm ci`, `composer check`, `composer audit`, `npm audit --omit=dev`; job kedua dengan service MariaDB menjalankan `check:mysql`. Bukti: `.github/workflows/ci.yml` dibuat + `composer check` ✔ (2026-11-26).
- [x] **T-007 Daftarkan proyek di standar-ui-project** (P1) — **Minta izin pemilik dulu** (repo lain). Berkas: `~/Projects/standar-ui-project/projects/madrasah-terpadu/{README.md,AGENTS.md,overrides.md,routes.md,theme.md}` sesuai `projects/README.md` di repo itu. KP: `overrides.md` = DESIGN §8, `theme.md` = token DESIGN §2, `routes.md` = area & layout ARCHITECTURE §4; tanpa menyalin aturan global; catat di `CHANGELOG.md` repo standar bila aturannya meminta. Bukti: 5 berkas + `CHANGELOG.md` ditulis di `~/Projects/standar-ui-project` dengan izin pemilik (2026-11-26).

---

## Fase 1 — Cangkang aplikasi, autentikasi, hak akses

- [x] **T-101 Matikan registrasi publik & verifikasi email wajib** (P0, ADR-014) — Ref: PRD §3, FR-AUTH. Berkas: `config/fortify.php`, `routes/`, tes `RegistrationTest`/`EmailVerificationTest`.
      KP: `Features::registration()` dan `emailVerification()` dinonaktifkan; rute `/register` 404; middleware `verified` dihapus dari grup rute aplikasi; tes registrasi/verifikasi dihapus **dengan** alasan dicatat di laporan tugas dan usulan pesan commit (ADR-014). Tes: `/register` → 404. Bukti: `tests/Feature/Auth/PublicRegistrationDisabledTest.php` + Pint/Larastan/Pest (69 tes)/tsc/`npm run build` ✔ (2026-11-26).
- [x] **T-102 Kolom pengguna** (P0) — Ref: SCHEMA §A `users`. Berkas: migration baru, `User` model, factory.
      KP: `username`, `phone`, `is_active`, `last_login_at`, softDeletes, `email` nullable (tanpa `must_change_password`, ADR-015); trait `HasRoles`; normalisasi nomor HP ke format `08…`/`62…` konsisten (satu helper `PhoneNumber::normalize`). Tes: factory valid; HP dinormalisasi; unik. Bukti: `tests/Unit/Support/PhoneNumberTest.php` + `tests/Feature/Auth/UserColumnsTest.php` + Pint/Larastan/Pest (81 tes)/tsc ✔ (2026-11-26).
- [x] **T-103 Login dengan username / nomor HP / NIS** (P0, FR-AUTH) — Ref: ARCHITECTURE §9. Berkas: `FortifyServiceProvider` (`Fortify::authenticateUsing`), halaman `auth/login.tsx`.
      KP: satu field "Username, nomor HP, atau NIS"; NIS memetakan ke wali utama siswa itu; akun nonaktif ditolak dengan pesan generik; rate limit 5/menit per identitas+IP; sesi diregenerasi; `last_login_at` diperbarui; percobaan gagal masuk `audit_logs` (`login_failed`, tanpa password). Tes: tiap jenis identitas; nonaktif; rate limit; audit. Bukti: `tests/Feature/Auth/LoginIdentityTest.php` + Pint/Larastan/Pest (87 tes)/tsc ✔ (2026-11-26).
- [x] **T-104 2FA wajib untuk admin & bendahara** (P0) — Berkas: middleware `EnsureTwoFactorForPrivilegedRoles`. KP: admin/bendahara tanpa 2FA diarahkan ke pengaturan keamanan sampai 2FA aktif; peran lain opsional. Tes: admin tanpa 2FA → redirect; dengan 2FA → lolos. Bukti: `tests/Feature/Auth/PrivilegedTwoFactorTest.php` + Pint/Larastan/Pest (92 tes)/tsc ✔ (2026-11-26).
- [x] **T-105 AuditLogger + `audit_logs`** (P0, FR-AUDIT) — Ref: REUSE_MAP §2. Berkas: migration, `app/Services/Audit/AuditLogger.php`, `AuditLog` model.
      KP: `record(action, subject, before, after)` mengambil user/IP/UA dari request bila ada; menyaring kunci sensitif (`password`, `token*`, `two_factor*`, `remember_token`); dipanggil di dalam transaksi pemanggil. Tes: filter kunci sensitif; tercatat dengan user; tanpa request (job/console) tetap jalan. Bukti: `tests/Feature/Audit/AuditLoggerTest.php` + Pint/Larastan/Pest (96 tes)/tsc ✔ (2026-11-26).
- [x] **T-106 Token desain & font** (P0) [UI] — Ref: DESIGN §2. Berkas: `resources/css/app.css`, `package.json`.
      KP: token light/dark sesuai DESIGN §2 (termasuk `--success`, `--warning`, `--accent-attention`); `@fontsource-variable/plus-jakarta-sans`; utilitas `tabular-nums`; tidak ada warna mentah di komponen bawaan starter kit yang dipakai; mode gelap/terang/sistem tetap berfungsi. Tes: `npm run build` sukses; pemeriksaan visual halaman login & dashboard (light/dark). Bukti: `npm run build` ✔ (Plus Jakarta Sans ter-bundle), `tsc --noEmit` ✔, `vp check` pada 8 berkas CSS/TSX ✔, Pint/Larastan/Pest (96 tes) ✔ (2026-11-26).
- [x] **T-107 Peran & izin + seeder** (P0, FR-RBAC) — Ref: PRD §3. Berkas: `database/seeders/RolesAndPermissionsSeeder.php`, `app/Enums/Permission.php`.
      KP: semua peran `App\Enums\Role` dibuat; izin per modul (`students.view`, `students.manage`, `invoices.issue`, `payments.receive`, `behavior.record`, `behavior.void`, `rewards.approve`, `attitude.lock`, `settings.manage`, ...) dipetakan ke peran sesuai PRD §3; seeder idempoten. Tes: setiap peran punya izin yang diharapkan (tabel data-driven). Bukti: `tests/Feature/Auth/RolesAndPermissionsSeederTest.php` (9 tes) + Pint/Larastan/Pest (105 tes)/tsc ✔ (2026-11-26).
- [ ] **T-108 `app:create-admin` + `ProductionSeeder`** (P0) — Berkas: `app/Console/Commands/CreateAdmin.php`, `database/seeders/ProductionSeeder.php`.
      KP: perintah interaktif (nama, username, password tersembunyi ≥ 12) + opsi non-interaktif; idempoten; `ProductionSeeder` hanya peran, izin, unit dasar (MI, RA, TPQ), surah — **tanpa akun**. Tes: membuat admin; menolak password pendek/duplikat.
- [ ] **T-109 `EnsureActiveUser` + `SecurityHeaders`** (P0) — Ref: ARCHITECTURE §9. KP: akun nonaktif yang sedang login dikeluarkan pada request berikutnya; header keamanan di semua respons web; CSP mengizinkan Vite dev, build, dan `app(.sandbox).midtrans.com` hanya untuk script Snap. Tes: header ada; pengguna dinonaktifkan → logout.
- [ ] **T-110 Arahkan pasca-login sesuai peran** (P0) — Ref: ARCHITECTURE §4. KP: `/dashboard` → `/wali` / `/gerbang` / `/kantin` / `/admin` sesuai peran; pengguna tanpa peran melihat `NoAccessState`. Tes: per peran.
- [ ] **T-111 Layout staf + navigasi dari server** (P0) [UI] — Ref: DESIGN §3, standar 02. Berkas: `layouts/staff-layout.tsx`, `HandleInertiaRequests` (`navigation`), `app/Support/Navigation.php`.
      KP: topbar dengan grup menu rata tengah (desktop), sheet + hamburger (<1024 px), breadcrumb, pemilih unit & tahun ajaran aktif (disimpan di sesi), menu aktif bertahan di semua ukuran; item menu hanya muncul bila pengguna punya izin rute tujuannya. Tes: guru tidak menerima item menu Keuangan; tiap item menu → rute yang lolos otorisasi untuk peran itu (tes iterasi semua peran × semua item).
- [ ] **T-112 Komponen aplikasi dasar** (P0) [UI] — Ref: DESIGN §4–5. Berkas: `components/app/*`, `lib/format.ts`.
      KP: `PageHeader`, `DataTable` (server pagination, sort, skeleton, empty), `RowActions`, `ExportButtons`, `FormDialog`, `ConfirmDialog`, `MoneyText`, `DateText`, `StatusBadge`, `EmptyState`, `ErrorState`, `NoAccessState`, toast (sonner) dari flash server; `format.ts` (`formatRupiah`, `formatDate`, `formatTime` dalam WITA, memakai `Intl` dengan locale `id-ID` dan zona `Asia/Makassar`); padanan PHP `App\Support\Money` untuk PDF/notifikasi. Tes: Pest untuk `App\Support\Money` (0, ribuan, jutaan, negatif); `format.ts` dijaga `tsc` + pemeriksaan visual (Vitest tidak dipasang).
- [ ] **T-113 Halaman login & akun** (P0) [UI] — Ref: DESIGN §3 Auth, standar 04. KP: form terpusat, logo + nama yayasan dari pengaturan, show/hide password, pesan generik; halaman ganti password sukarela (password lama wajib), profil (nama, HP). **Tanpa** paksa ganti password (ADR-015). Tes: ganti password butuh password lama; login dengan password baru berhasil.
- [ ] **T-114 Pengguna staf (CRUD)** (P0) — Berkas: `Admin/UserController`, `pages/admin/users`. KP: tambah/ubah/nonaktifkan staf, atur peran (multi), reset password (password acak ditampilkan sekali ke admin); admin tidak bisa menonaktifkan dirinya sendiri; audit. Tes: otorisasi; audit; tidak bisa menghapus admin terakhir.

---

## Fase 2 — Data induk

- [ ] **T-201 Pengaturan (`settings`) + `SettingsRepository`** (P0) — Ref: PRD §4.13. KP: kunci identitas (nama aplikasi, yayasan, alamat, kontak, logo, favicon), cache + invalidasi, audit; halaman Pengaturan > Identitas. Tes: simpan/baca/cache; non-admin 403.
- [ ] **T-202 Unit + pengaturan jam presensi** (P0) — Ref: SCHEMA §A `units`. KP: CRUD unit (MI/RA/TPQ di seeder), jam masuk/terlambat/pulang/alpa per unit dengan validasi urutan waktu. Tes: urutan jam tidak valid ditolak.
- [ ] **T-203 Tahun ajaran & semester** (P0) — Ref: REUSE_MAP §2. KP: format `YYYY/YYYY`, semester ganjil/genap, maks satu aktif (transaksi), tidak bisa dihapus bila dipakai. Tes: mengaktifkan satu menonaktifkan lainnya.
- [ ] **T-204 Rombel** (P0) [UI] — KP: CRUD per unit+tahun ajaran, wali kelas (guru), unik nama per unit+tahun; salin rombel dari tahun sebelumnya. Tes: unik; hanya guru yang bisa jadi wali kelas.
- [ ] **T-205 Siswa + enrollment** (P0) [UI] — Ref: SCHEMA §A. KP: CRUD, foto (disk privat, kompres ≤ 300 KB), status, pindah rombel (enrollment tahun aktif), nama panggilan; pencarian nama/NIS; filter unit/rombel/status; paginasi; nonaktifkan bukan hapus. Tes: NIS unik; satu enrollment per tahun ajaran; foto hanya bisa diakses staf/wali terkait.
- [ ] **T-206 Wali + hubungan anak** (P0) [UI] — KP: buat akun wali (username = nomor HP ternormalisasi, password awal acak; tanpa paksa ganti password, ADR-015), hubungkan ke banyak anak, wali utama, cetak "surat akun wali" (PDF: username + password awal, sekali tampil). Tes: wali melihat hanya anak terhubung; password awal tidak tersimpan plaintext.
- [ ] **T-207 Impor Excel siswa + wali** (P0) — Ref: REUSE_MAP §2 (StudentImporter). Dep: `openspout/openspout`. KP: template unduhan; ≤ 1.500 baris, ≤ 2 MB; pratinjau (baru/ubah/galat per baris); transaksi per chunk; hasil `{created, updated, failed[{row, error}]}`; membuat/menautkan wali berdasarkan nomor HP; audit ringkas. Tes: baris galat dilaporkan, sisanya masuk; impor ulang idempoten.
- [ ] **T-208 Tampilan (branding)** (P1) [UI] — Ref: DESIGN §2, standar 05. KP: unggah logo/favicon, pilih warna utama; turunan hover/active/focus otomatis; pemeriksaan kontras AA (tolak bila gagal); reset ke default; berlaku untuk light & dark. Tes: kontras rendah ditolak.
- [ ] **T-209 Profil staf + penugasan** (P1) — KP: unit, NIP, jabatan; guru dapat ditugaskan ke rombel (wali kelas) dan ke kelompok tahfidz (dipakai Fase 11).

---

## Fase 3 — Portal wali & PWA

- [ ] **T-301 Layout wali** (P0) [UI] — Ref: DESIGN §3 Wali + §8. KP: mobile-first, navigasi bawah 4 item (Beranda, Tagihan, Riwayat, Akun), pemilih anak bila >1 anak (diingat di sesi), lonceng kotak masuk; target sentuh ≥ 44 px. Tes: wali dengan 2 anak bisa berpindah; wali tanpa anak melihat empty state yang menyuruh menghubungi sekolah.
- [ ] **T-302 `EnsureGuardianOwnsStudent` + `StudentPolicy`** (P0) — KP: semua rute `/wali/anak/{student}/...` memvalidasi kepemilikan; anak lain → 404 (bukan 403, agar tidak membocorkan keberadaan). Tes: data-driven untuk semua rute wali yang ada (diperbarui setiap fase).
- [ ] **T-303 Beranda wali (kerangka)** (P0) [UI] — Ref: PRD §4.12, DESIGN §3. KP: kartu disusun berdasar urgensi; kartu modul yang belum dibangun **tidak ditampilkan** (bukan placeholder); data via Resource; cache 60 detik per anak. Tes: hanya data anak sendiri; kartu tersembunyi bila tidak ada data.
- [ ] **T-304 Manifest + service worker + pemasangan** (P0) — Berkas: `public/manifest.webmanifest`, `public/sw.js`, `resources/js/lib/pwa.ts`. KP: ikon 192/512/maskable dari logo pengaturan; `start_url=/wali`; service worker **tidak** meng-cache respons berisi data (hanya halaman offline statis + aset build); ajakan "Pasang aplikasi" muncul sekali di beranda wali bila `beforeinstallprompt` tersedia, dan petunjuk singkat untuk iOS (Bagikan → Tambahkan ke Layar Utama). Tes: Lighthouse PWA installable (manual, catat skor); `sw.js` disajikan tanpa cache.
- [ ] **T-305 Halaman offline** (P1) — KP: saat offline, navigasi menampilkan halaman offline sederhana dengan tombol coba lagi; tidak menampilkan data basi.

---

## Fase 4 — Notifikasi

- [ ] **T-401 Web Push** (P0) — Dep: `laravel-notification-channels/webpush`. Ref: ARCHITECTURE §5, ADR-005. KP: tabel subscription; perintah membuat VAPID (`webpush:vapid`); `POST/DELETE /push/subscriptions` (auth, throttle); `lib/push.ts` meminta izin **hanya setelah tombol ditekan** (bukan otomatis saat halaman dibuka); `sw.js` menampilkan notifikasi dengan `tag`, ikon, dan membuka URL saat diklik; subscription 404/410 dihapus. Tes: simpan/hapus subscription; notifikasi uji ke channel WebPush (fake).
- [ ] **T-402 Kotak masuk** (P0) [UI] — KP: tabel `notifications`; daftar per wali (paginasi), tanda belum dibaca, tandai semua dibaca, tautan ke halaman terkait; jumlah belum dibaca di lonceng (shared prop ringan). Tes: wali hanya melihat notifikasinya.
- [ ] **T-403 Preferensi notifikasi** (P0) [UI] — Ref: PRD §4.3, `config/sekolah.php`. KP: toggle per kategori; `billing` & `account` terkunci aktif; status izin push perangkat ini + tombol aktifkan/nonaktifkan. Tes: kategori nonaktif → `via()` tanpa WebPush tetapi tetap `database`.
- [ ] **T-404 `GuardianNotifier` + kelas notifikasi dasar** (P0) — Ref: ARCHITECTURE §3, §5. KP: `notifyStudentGuardians(Student, Notification)`; semua notifikasi domain `ShouldQueueAfterCommit`, antrean `notifications`; payload tanpa data sensitif; `WhatsAppChannel` no-op terdaftar tetapi tidak dipakai. Tes: rollback transaksi → tidak ada notifikasi; dua wali → dua notifikasi.
- [ ] **T-405 Notifikasi uji dari admin** (P1) — KP: admin dapat mengirim notifikasi uji ke satu wali untuk membantu dukungan teknis ("notifikasi tidak masuk").

---

## Fase 5 — Presensi gerbang

- [ ] **T-501 Kartu QR siswa** (P0) — Ref: ADR-008, SCHEMA §C. KP: terbitkan/cabut/terbitkan ulang; token 32 byte acak, simpan hash SHA-256; maks satu kartu aktif; audit. Tes: token mentah tidak tersimpan; kartu dicabut tidak bisa dipakai.
- [ ] **T-502 `AttendanceClassifier` + `GateScanService`** (P0) [mysql] — Ref: PRD §4.2, ARCHITECTURE §8. KP: Domain murni mengklasifikasi (masuk tepat waktu / terlambat / pulang / duplikat < 120 dtk / di luar jam / hari libur) berdasarkan jam unit (WITA); Service menyimpan `gate_scans` + upsert `attendance_days` dalam transaksi; notifikasi masuk/pulang ke wali (afterCommit). Tes: tabel kasus jam (data-driven) untuk classifier; scan ganda bersamaan → satu `attendance_days`.
- [ ] **T-503 Cetak kartu** (P0) — Dep: npm `qrcode` (atau render QR di PHP bila sudah ada pustaka yang diizinkan; pilih salah satu dan catat). KP: PDF kartu ukuran ID-1 (85,6 × 54 mm), 10 per A4, berisi foto, nama, NIS, rombel, unit, QR; cetak per rombel atau per siswa. Tes: PDF terbuat untuk 30 siswa < 10 detik.
- [ ] **T-504 Layar scan gerbang** (P0) [UI] — Dep: `qr-scanner` (fallback kamera). Ref: DESIGN §3 Kiosk. KP: input selalu fokus (scanner barcode); tombol kamera (BarcodeDetector → fallback); hasil besar + suara; 10 scan terakhir; status koneksi; throttle 300/menit/petugas; respons server ≤ 300 ms (ukur di tes dengan 1.000 siswa). Tes: endpoint scan (sukses, terlambat, duplikat, kartu dicabut, bukan petugas → 403).
- [ ] **T-505 Kalender sekolah** (P0) — KP: hari libur/acara per unit atau semua; akhir pekan dikonfigurasi per unit; scan & alpa otomatis menghormati libur. Tes: tidak ada alpa di hari libur.
- [ ] **T-506 Izin/sakit dari wali** (P0) [UI] — Ref: PRD §4.2. KP: wali mengajukan (rentang tanggal, jenis, alasan, foto opsional ≤ 2 MB, maks 3 hari ke belakang); wali kelas/admin menyetujui/menolak dengan catatan; disetujui → `attendance_days` hari itu jadi `sick/excused`; notifikasi keputusan ke wali. Tes: tidak bisa mengajukan untuk anak lain; tumpang tindih tanggal ditolak.
- [ ] **T-507 Alpa otomatis** (P0) — Berkas: `MarkDailyAbsences` (scheduler tiap 15 menit pada hari sekolah). KP: setelah jam `absent_after` unit, siswa aktif tanpa scan & tanpa izin → `absent` (`decided_by=system`) + notifikasi; idempoten; tidak jalan di hari libur. Tes: dijalankan dua kali → satu notifikasi.
- [ ] **T-508 Rekap presensi staf** (P0) [UI] — KP: harian per rombel (hadir/terlambat/izin/sakit/alpa/belum tap) dengan koreksi manual oleh wali kelas (alasan wajib, audit); bulanan per siswa. Tes: koreksi tercatat di audit.
- [ ] **T-509 Presensi di portal wali** (P0) [UI] — KP: kartu "Hari ini" (jam masuk/pulang, status); riwayat bulanan bentuk kalender + daftar; tombol ajukan izin. Tes: hanya anak sendiri.

---

## Fase 6 — Keuangan sekolah

- [ ] **T-601 Jenis tagihan** (P0) [UI] — Ref: PRD §4.4. KP: CRUD per unit, kategori, bulanan/sekali, nominal default; nonaktifkan bukan hapus bila sudah dipakai. Tes: tidak bisa hapus bila ada tagihan.
- [ ] **T-602 Penerbitan tagihan (`BillingRunService`)** (P0) [mysql] [UI] — Ref: PRD §4.4, SCHEMA §D. KP: target unit/tingkat/rombel/siswa terpilih/satu siswa; rentang bulan untuk bulanan; jatuh tempo (tanggal tetap per bulan atau tanggal tertentu); **pratinjau** per siswa (akan dibuat / sudah ada / dilewati karena nonaktif) dengan nominal dapat diubah per siswa; eksekusi dalam chunk + transaksi; unik (`student_id`,`fee_type_id`,`period`) — tagihan `once` memakai `period` = tanggal mulai tahun ajaran aktif; ringkasan `billing_runs`; audit. Tes: terbit ulang tidak menggandakan; target rombel hanya siswa aktif rombel itu; nominal per siswa tersimpan.
- [ ] **T-603 Daftar & detail tagihan** (P0) [UI] — KP: filter unit/rombel/jenis/status/periode/jatuh tempo, pencarian siswa, "menunggak" sebagai filter turunan; detail tagihan dengan riwayat penyesuaian & pembayaran; kartu tagihan per siswa (total, terbayar, sisa). Tes: filter menunggak benar di batas tanggal (WITA).
- [ ] **T-604 Penyesuaian & pembatalan tagihan** (P0) — KP: diskon/pembebasan/koreksi dengan alasan wajib (append-only), tidak boleh membuat sisa < 0; pembatalan hanya bila belum ada pembayaran; status dihitung ulang; audit. Tes: penyesuaian melebihi sisa ditolak; tagihan berpembayaran tidak bisa dibatalkan.
- [ ] **T-605 Pembayaran kasir (tunai/transfer, cicilan)** (P0) [mysql] [UI] — KP: pilih siswa → daftar tagihan terbuka → nominal per tagihan (boleh sebagian) → metode → simpan; satu `payments` + `payment_allocations`; nomor kuitansi berurutan per tahun (`KW-2026-000123`), aman dari balapan; status tagihan diperbarui; notifikasi `InvoicePaid` ke wali; audit. Tes: alokasi melebihi sisa ditolak; dua kasir bersamaan → nomor kuitansi tidak ganda.
- [ ] **T-606 Pembalikan pembayaran** (P0) — KP: hanya bendahara/admin, alasan wajib; alokasi dibalik, status tagihan dihitung ulang; pembayaran Midtrans tidak dapat dibalik dari sini (rujuk dashboard Midtrans); audit. Tes: pembalikan → status kembali `unpaid/partial`.
- [ ] **T-607 Kuitansi PDF** (P0) — Dep: `barryvdh/laravel-dompdf`. KP: kuitansi berisi identitas yayasan, siswa, rincian alokasi, metode, kasir/“Pembayaran online”, nomor kuitansi; dapat diunduh wali untuk anaknya sendiri. Tes: wali lain → 404.
- [ ] **T-608 Tagihan di portal wali** (P0) [UI] — KP: daftar per anak (jatuh tempo terdekat di atas, menunggak ditandai `--accent-attention`), total keluarga, riwayat pembayaran + kuitansi; tombol bayar online (aktif setelah Fase 7). Tes: total keluarga = jumlah sisa semua anak.
- [ ] **T-609 Pengingat jatuh tempo** (P1) — KP: scheduler harian 07.00 WITA: notifikasi H-3 dan H+1 jatuh tempo, maks satu notifikasi per tagihan per jenis. Tes: idempoten.
- [ ] **T-610 Saldo awal migrasi** (P0) — Ref: PRD §8 no. 10. KP: impor Excel tunggakan dari aplikasi lama sebagai tagihan dengan jenis "Saldo Awal" dan deskripsi asli; pratinjau; audit. Tes: impor ulang idempoten.
- [ ] **T-611 Periode ujian & syarat tagihan** (P0) [UI] — Ref: PRD §4.4, ADR-016, SCHEMA §D. KP: bendahara/admin membuat periode (unit MI dulu, `cbt|paper`, tanggal) dan memilih jenis tagihan + batas periode yang wajib lunas; `ExamEligibilityResolver` (Domain) menghitung layak/tidak dari tagihan (cicilan sebagian = belum layak). Tes: unit resolver (lunas, sebagian, tagihan di luar syarat diabaikan, dibatalkan diabaikan); pembayaran masuk → langsung layak.
- [ ] **T-612 Dispensasi ujian** (P0) [UI] — Ref: ADR-016. KP: hanya admin memberi/mencabut dispensasi per siswa per periode, alasan wajib, `AuditLogger`; tidak mengubah tagihan. Tes: bendahara/guru → 403; dicabut → tidak layak lagi.
- [ ] **T-613 Daftar kelayakan & peringatan ujian kertas** (P0) [UI] — KP: daftar per rombel (layak/tidak/dispensasi) dengan pola antrean (mockup menu 13); ekspor PDF/Excel untuk panitia; wali kelas baca saja. Tes: otorisasi; ekspor aman formula injection.
- [ ] **T-614 Kartu & notifikasi wali untuk syarat ujian** (P0) [UI] — KP: kartu "Perlu tindakan" di beranda wali selama periode aktif bila anak belum layak (tagihan yang harus dilunasi + Bayar); notifikasi `billing` H-7 dan H-1 (maks satu per hari). Tes: anak layak/dispensasi → tidak ada kartu & notifikasi.

---

## Fase 7 — Pembayaran online Midtrans

Baca `PAYMENTS.md` seluruhnya sebelum mulai.

- [ ] **T-701 `MidtransClient`** (P0) — Ref: PAYMENTS §2–3, ADR-007. KP: `createSnapTransaction(array)`, `getStatus(orderId)`; base URL dari `is_production`; Basic Auth Server Key; timeout 10 dtk, retry 2x untuk 5xx; exception bertipe; tanpa log Server Key. Binding `MidtransSignature` di container dari `config('services.midtrans.server_key')`. Tes: `Http::fake` untuk sukses, 4xx, 5xx, timeout.
- [ ] **T-702 `PaymentOrderService` + halaman bayar wali** (P0) [mysql] [UI] — Ref: PAYMENTS §3. KP: buat order untuk tagihan terpilih (penuh, milik anak sendiri, tidak ada di order pending lain) atau top-up; `gross_amount` dari DB; `item_details` cocok; simpan token; muat `snap.js` hanya di halaman ini; callback browser hanya navigasi ke halaman status; halaman status polling ringan status order (tiap 5 dtk, maks 2 menit). Throttle 10/menit/wali. Tes: skenario PAYMENTS §6 no. 12, 13; tagihan dalam order pending tidak bisa diorder lagi.
- [ ] **T-703 Webhook + `ProcessMidtransNotification`** (P0) [mysql] — Ref: PAYMENTS §4. KP: seluruh langkah §4 termasuk `MidtransStatusMapper` (Domain); antrean `payments`; idempoten. Tes: skenario PAYMENTS §6 no. 1–11, 14.
- [ ] **T-704 Kedaluwarsa order** (P0) — Ref: PAYMENTS §5. KP: job tiap 15 menit; cek status sebelum menandai expired. Tes: order sudah dibayar tidak ditandai expired.
- [ ] **T-705 Rekonsiliasi** (P0) [UI] — KP: `payments:reconcile`; halaman bendahara "Pembayaran online" (filter status/tanggal, detail order + payload terakhir tanpa data sensitif, tombol "Cek status sekarang"); selisih memicu notifikasi bendahara. Tes: selisih terdeteksi.
- [ ] **T-706 `payments:check-config`** (P0) — Ref: PAYMENTS §2. KP: gagal bila production + key sandbox, sandbox + key production, key kosong, atau `APP_URL` bukan HTTPS di production. Tes: tiap kombinasi.
- [ ] **T-707 Peringatan bendahara** (P0) — KP: notifikasi ke bendahara untuk `amount_mismatch`, `needs_review`, kelebihan bayar (ADR-009 **[TERBUKA U-05]** — implementasikan default saldo kantin, beri feature flag). Tes: tiap jenis peringatan.
- [ ] **T-708 Uji sandbox manual** (P0) — Ref: PAYMENTS §7. KP: semua skenario §6 yang bisa disimulasikan dijalankan di sandbox dengan tunnel; hasil (tanggal, order_id, hasil) ditulis di bukti tugas.

---

## Fase 8 — Dompet kantin

- [ ] **T-801 `WalletService` (ledger)** (P0) [mysql] — Ref: ADR-006, ARCHITECTURE §7, SCHEMA §F. KP: dompet dibuat otomatis untuk siswa aktif; `topUp`, `purchase`, `reverse`, `adjust` dalam transaksi + `lockForUpdate`; `idempotency_key` unik; `balance_after` benar; `InsufficientBalanceException`, `DailyLimitExceededException`, dompet beku ditolak. Tes: saldo = jumlah entri; pembelian bersamaan tidak membuat saldo negatif; idempotensi.
- [ ] **T-802 `wallet:verify-balances`** (P0) — KP: harian; bandingkan cache vs jumlah ledger; selisih → log + notifikasi admin, **tanpa** koreksi otomatis. Tes: selisih terdeteksi.
- [ ] **T-803 POS kantin** (P0) [mysql] [UI] — Ref: PRD §4.6, DESIGN §3 Kiosk. KP: scan kartu → identitas + saldo + voucher + sisa limit; input nominal (rincian item opsional); `client_uuid` dari browser untuk idempotensi; voucher dipakai dulu; konfirmasi bila > Rp50.000; notifikasi jajan ke wali; throttle; koreksi oleh kasir ≤ 15 menit (transaksinya sendiri), oleh bendahara kapan pun (alasan wajib). Tes: limit harian (WITA) dihitung dari pembelian hari itu dikurangi pembalikan; klik ganda → satu transaksi.
- [ ] **T-804 Top-up tunai** (P0) — KP: bendahara/petugas berizin; kuitansi sederhana; notifikasi ke wali. Tes: otorisasi; idempoten.
- [ ] **T-805 Top-up online** (P0) — Ref: T-702 (purpose `wallet_topup`). KP: wali memilih nominal cepat (Rp20.000/50.000/100.000) atau isi sendiri dalam batas; saldo bertambah hanya lewat webhook. Tes: skenario PAYMENTS §6 no. 11.
- [ ] **T-806 Dompet di portal wali** (P0) [UI] — KP: saldo, voucher aktif, jajan hari ini vs limit, riwayat transaksi; ubah limit harian (≤ batas sekolah); bekukan dompet sementara (mis. kartu hilang). Tes: limit di atas batas ditolak.
- [ ] **T-807 Laporan kantin** (P1) [UI] — KP: penjualan harian per kasir, total top-up per metode, saldo mengendap, pemakaian voucher (subsidi sekolah) terpisah. Ekspor PDF/Excel.

---

## Fase 9 — Buku Saku BK & reward (berbasis SIPESAN)

- [ ] **T-901 Aturan poin** (P0) [UI] — Ref: PRD §4.7, REUSE_MAP §1. KP: CRUD per unit/semua unit; kategori pelanggaran/prestasi/kebaikan; level prestasi; bobot > 0; nonaktifkan bukan hapus; impor dari Excel tata tertib (kolom kode, nama, kategori, poin). Tes: kode unik per unit.
- [ ] **T-902 Catatan perilaku + ledger poin** (P0) [mysql] [UI] — KP: guru/BK mencatat satu atau **banyak siswa sekaligus** (mis. satu kelas terlambat apel); poin di-snapshot; `point_entries` per akun (`violation`/`merit`); bukti foto opsional (privat); pembatalan oleh BK/admin dengan alasan → entri pembalik; notifikasi ke wali (nama pencatat disembunyikan default **[U-06]**). Tes: pembatalan memulihkan saldo; bobot aturan berubah tidak mengubah catatan lama.
- [ ] **T-903 Ambang tindakan & tindak lanjut** (P0) [UI] — Ref: SCHEMA §G. KP: master ambang per unit; `ThresholdEvaluator` (Domain) dijalankan setelah setiap entri pelanggaran; membuat `discipline_followups` sekali per ambang per tahun; notifikasi wali bila `notify_guardian`; daftar tindak lanjut terbuka untuk BK + tandai selesai dengan catatan. Tes: melewati dua ambang sekaligus → dua tindak lanjut; pembatalan catatan tidak menghapus tindak lanjut yang sudah selesai.
- [ ] **T-904 Penawaran reward, penukaran, voucher** (P0) [mysql] [UI] — Ref: PRD §4.8. KP: master penawaran (biaya poin, nilai voucher, masa berlaku, stok); wali/BK mengajukan; BK menyetujui → kurangi poin kebaikan (entri), kurangi stok, terbitkan `canteen_vouchers`; tolak dengan catatan; poin tidak cukup → ditolak; notifikasi. Tes: persetujuan ganda bersamaan → satu voucher; stok habis ditolak.
- [ ] **T-905 Pemberitahuan penawaran tersedia** (P1) — KP: setelah entri poin kebaikan, bila saldo ≥ biaya penawaran aktif yang belum pernah diberitahukan → notifikasi sekali (`reward_offer_notices`). Tes: tidak berulang.
- [ ] **T-906 Buku saku di portal wali** (P0) [UI] — KP: ringkasan poin pelanggaran & kebaikan tahun ini, riwayat catatan, tindak lanjut yang melibatkan wali, penawaran reward + tombol ajukan, voucher aktif. Tes: hanya anak sendiri.
- [ ] **T-907 Catatan dari daftar keterlambatan** (P1) — Ref: U-03. KP: dari rekap presensi "terlambat hari ini", BK memilih siswa dan membuat catatan pelanggaran sekaligus (aturan terpilih). Otomatisasi penuh hanya bila U-03 diputuskan "ya".

---

## Fase 10 — Pembiasaan (mutabaah)

- [ ] **T-1001 Item pembiasaan** (P0) [UI] — Ref: PRD §4.9, ADR-019. KP: CRUD per unit oleh admin, aspek spiritual/sosial, urutan (naik/turun), aktif; seeder lima item sholat **terkunci** (tidak bisa dihapus/dinonaktifkan/diganti nama, hanya urutan; ikon gembok + tooltip "Item wajib sekolah"); item yang punya catatan hanya dinonaktifkan. Tes: urutan stabil; item terkunci ditolak di Service + Policy (bukan hanya UI).
- [ ] **T-1002 Centang oleh wali** (P0) [UI] — KP: daftar item hari ini per anak, centang/batal centang sampai diverifikasi, maks 3 hari ke belakang, simpan cepat (optimistic UI + rollback bila gagal). Tes: tidak bisa mengubah log yang sudah diverifikasi; tanggal > 3 hari ditolak.
- [ ] **T-1003 Verifikasi oleh wali kelas** (P0) [UI] — KP: tabel rombel × item untuk satu tanggal; verifikasi massal per siswa/per item; tolak dengan catatan; notifikasi ringkas ke wali bila ada penolakan. Tes: guru rombel lain 403.
- [ ] **T-1004 Rekap pembiasaan** (P1) [UI] — KP: persentase per siswa per aspek per bulan/semester (hanya terverifikasi); dipakai Fase 12.
- [ ] **T-1005 Streak → poin** (P2) — **[TERBUKA U-04]**. Jangan dikerjakan sebelum diputuskan.

---

## Fase 11 — Setoran hafalan & praktik ibadah

- [ ] **T-1101 Data surah** (P0) — KP: seeder 114 surah (nomor, nama Latin, nama Arab, jumlah ayat, juz awal) dari sumber data publik; tes memastikan total ayat 6.236. Tidak ada teks mushaf.
- [ ] **T-1102 Target hafalan** (P0) [UI] — KP: per unit + tingkat + tahun ajaran; rentang surah:ayat divalidasi `AyahRange`. Tes: rentang melewati jumlah ayat ditolak. **Tambahan ADR-017:** `kind` quran/hadith/prayer; target hadits/doa berupa daftar materi.
- [ ] **T-1103 Rubrik berversi** (P0) — Ref: REUSE_MAP §3. KP: port `RubricScorer` + aturan versi; versi terbit immutable; seeder rubrik default (Kelancaran, Tajwid, Makhraj) sebagai draf yang harus diterbitkan admin. Tes: port tes perhitungan dari penilaian-tahfidz (hasil identik).
- [ ] **T-1104 Input setoran** (P0) [UI] — KP: guru memilih siswa (kelompoknya), jenis ziyadah/murajaah, rentang ayat, nilai per kriteria (keyboard-first: Tab antar-kriteria, Enter simpan & lanjut siswa berikut), nilai akhir & predikat dihitung server; notifikasi ke wali. Tes: guru bukan pengampu 403; nilai di luar rentang ditolak. **Tambahan ADR-017/018:** pilih jenis Qur'an/Hadits/Do'a (hadits/doa: pilih materi, tanpa ziyadah/murajaah); kriteria mode pengurangan memakai `MistakeCounter` (PageDown +1, PageUp −1 pada kriteria aktif, tidak aktif saat fokus di kolom teks; tombol ± di HP). Tes: validasi per jenis di Service; tombol keyboard mengubah kriteria aktif saja; batas 0..maks.
- [ ] **T-1105 Progres hafalan** (P0) [UI] — KP: `memorized_ayahs` diperbarui dari ziyadah lulus; progres % terhadap target; tampilan wali (progres + riwayat setoran) dan guru (per kelompok). Tes: ayat yang sama tidak dihitung dua kali. **Tambahan ADR-017:** progres per jenis; hadits/doa = materi lulus / materi target; tab Qur'an/Hadits/Do'a di portal wali.
- [ ] **T-1106 Praktik ibadah (PIB)** (P1) [UI] — Ref: REUSE_MAP §2. KP: port kurikulum bab/subbab/materi + `PibScore` (90 − kesalahan) + input nilai per rombel; rekap. Tes: port tes `ScoreCalculator` PIB. **Tambahan ADR-018:** input kesalahan memakai `MistakeCounter`.
- [ ] **T-1107 Materi hadits & doa** (P0) [UI] — Ref: ADR-017, SCHEMA `memorization_materials`. KP: CRUD oleh admin (judul, teks Arab RTL, terjemahan, sumber/perawi, urutan, aktif); materi yang punya setoran hanya dinonaktifkan. Tes: hanya admin; teks Arab tersimpan utuh (UTF-8).
- [ ] **T-1108 Komponen `MistakeCounter`** (P0) [UI] — Ref: ADR-018, DESIGN §4. KP: dipakai T-1104 & T-1106; satu kriteria aktif; PageDown/PageUp, panah kiri/kanan pindah kriteria; tombol ± ≥ 44 px; `aria-live`. Tes: komponen (tsc + uji perilaku bila ada alat), batas nilai.

---

## Fase 12 — Nilai sikap sosial & spiritual

**Diblokir sampai U-01 diputuskan.**

- [ ] **T-1201 `AttitudeScoreCalculator`** (P0) — Ref: PRD §4.11. KP: rumus dari konfigurasi (bukan hardcode); input: rekap pembiasaan terverifikasi, poin semester; output skor + predikat + deskripsi otomatis; `inputs` disimpan untuk jejak. Tes: tabel kasus batas predikat.
- [ ] **T-1202 Hitung, koreksi, kunci** (P0) [UI] — KP: wali kelas menghitung untuk rombelnya, mengubah predikat final/deskripsi (alasan bila berbeda dari hitungan), mengunci; kepala unit menyetujui; setelah dikunci tidak bisa diubah kecuali dibuka kepala unit (audit). Tes: alur status draft → locked → approved.
- [ ] **T-1203 Cetak & tampilan wali** (P0) [UI] — KP: PDF lampiran rapor sikap per siswa/rombel; wali melihat setelah `approved`. Tes: wali tidak melihat draf.

---

## Fase 13 — Laporan & ekspor

Semua laporan: filter unit/rombel/periode, paginasi layar, ekspor PDF & Excel icon-only, hanya peran berizin. Tes per laporan: otorisasi + angka cocok dengan data uji.

- [ ] **T-1301 Laporan presensi** (P0) — harian, bulanan per rombel, per siswa.
- [ ] **T-1302 Laporan keuangan** (P0) — piutang per jenis/rombel, penerimaan per metode/periode, buku kas penerimaan, kartu tagihan siswa.
- [ ] **T-1303 Laporan BK** (P0) — catatan per periode, siswa dengan poin tertinggi, tindak lanjut terbuka, reward & voucher.
- [ ] **T-1304 Laporan kantin** (P1) — lihat T-807 bila belum.
- [ ] **T-1305 Laporan hafalan & PIB** (P1) — progres per kelompok/rombel, setoran per guru.
- [ ] **T-1306 Laporan pembiasaan** (P1) — keterlaksanaan per item/rombel.
- [ ] **T-1307 Jejak audit** (P1) [UI] — admin: filter pengguna/aksi/subjek/tanggal, detail before/after.

---

## Fase 14 — Mutu, deploy, serah terima

- [ ] **T-1401 Audit keamanan** (P0) — KP: jalankan checklist AI_RULES §5 ke semua rute (`php artisan route:list` → tabel rute × middleware × tes), `composer audit`, `npm audit`; uji IDOR wali untuk setiap rute `/wali`; hasil di `docs/security-review.md`.
- [ ] **T-1402 Backup** (P0) — Dep: `spatie/laravel-backup`. KP: sesuai DEPLOYMENT §6; notifikasi gagal ke admin. Tes: perintah backup berjalan di tes dengan disk fake.
- [ ] **T-1403 Uji pemulihan** (P0) — KP: restore ke server staging/lokal dari arsip, aplikasi berjalan, data cocok; catat tanggal di DEPLOYMENT §6.
- [ ] **T-1404 Kesiapan produksi** (P0) — KP: `payments:check-config` lulus; `APP_DEBUG=false`; cache konfigurasi; HTTPS; worker & cron aktif; `/up` terpantau.
- [ ] **T-1405 `app:health`** (P1) — KP: deteksi antrean macet (> 10 menit), scheduler tidak jalan, backup gagal, order pending > 48 jam, selisih saldo; notifikasi admin.
- [ ] **T-1406 Uji beban** (P0) — KP: 300 scan dalam 15 menit dan 50 transaksi POS beruntun di server target tanpa galat, p95 scan ≤ 300 ms (server); hasil dicatat. **Tambahan ADR-020:** skenario CBT 250 siswa: 250 login dalam 2 menit, sinkron jawaban p95 ≤ 500 ms (~25 req/detik), penutupan 250 sesi ≤ 2 menit, Wi-Fi putus 60 detik tanpa kehilangan jawaban, restart server/worker tanpa siswa keluar.
- [ ] **T-1407 Uji browser end-to-end** (P1) — Dep: `@playwright/test`. KP: wali login → bayar tagihan (sandbox, webhook disimulasikan) → kuitansi; petugas scan → wali dapat notifikasi di kotak masuk; kasir POS → saldo berkurang.
- [ ] **T-1408 Audit UI menyeluruh** (P0) [UI] — KP: setiap halaman lolos DESIGN §9 + antislop-ui; hasil per halaman dicatat; temuan diperbaiki.
- [ ] **T-1409 Aksesibilitas** (P0) — KP: Lighthouse a11y ≥ 95 untuk halaman utama wali & staf; navigasi keyboard penuh; kontras light/dark AA.
- [ ] **T-1410 Performa** (P0) — KP: LCP ≤ 2,5 s beranda wali (throttling Moto G Power 4G di Lighthouse); tidak ada query N+1 (strict mode di staging).
- [ ] **T-1411 Migrasi data awal** (P0) — KP: impor siswa + wali (T-207) dan saldo awal (T-610) dari data klien; laporan hasil impor diserahkan ke klien untuk diverifikasi.
- [ ] **T-1412 UAT per peran** (P0) — KP: skrip UAT untuk admin, bendahara, guru, BK, wali kelas, petugas gerbang, kasir, wali; klien menandatangani hasil.
- [ ] **T-1413 Dokumen serah terima** (P0) — KP: `docs/handover/` berisi panduan admin, panduan bendahara, panduan wali (singkat, bergambar tangkapan layar asli), catatan akses server (disimpan terpisah, **tidak** di repo), prosedur backup/restore.
- [ ] **T-1414 Deploy produksi** (P0) — Ref: DEPLOYMENT. KP: semua langkah DEPLOYMENT §2–7; transaksi nyata nominal kecil bersama bendahara; rekonsiliasi lulus.

---

## Fase 15 — Ujian CBT (ADR-020)

Bergantung pada Fase 2 (data induk) dan T-611–T-612 (kelayakan). Unit pertama MI. Rujukan: REUSE_MAP §5.

- [ ] **T-1501 Mapel & penugasan guru** (P0) [UI] — KP: CRUD mapel per unit; penugasan guru × mapel × rombel × tahun ajaran (pola CRUD). Tes: guru hanya melihat mapel/rombel yang ditugaskan.
- [ ] **T-1502 Login siswa NIS + PIN** (P0) — KP: guard `student` terpisah; PIN di-hash, dibuat/di-reset wali kelas/admin (ditampilkan sekali, bisa dicetak per rombel); rate limit per NIS; sesi diregenerasi; hanya rute `/ujian`. Tes: siswa tidak bisa membuka `/admin`/`/wali`; enumerasi NIS tidak bocor.
- [ ] **T-1503 Ujian** (P0) [UI] — KP: buat ujian (mapel, rombel, jendela, durasi, acak, bobot per tipe, token opsional, batas pelanggaran, **mode perangkat** lab/tablet/phone, periode ujian opsional, tampil-ke-wali); validasi tanggal/durasi. Tes: otorisasi guru pengampu; validasi.
- [ ] **T-1504 Bank soal** (P0) [UI] — KP: PG, menjodohkan, essay kertas; teks Arab RTL (`ArabicText`); gambar opsional ≤ 2 MB; impor Excel; soal **terkunci** setelah ujian mulai, koreksi kunci hanya admin + alasan + hitung ulang + audit. Tes: IDOR antar-guru (404); kunci jawaban tidak ada di respons siswa; XSS teks soal di-escape.
- [ ] **T-1505 Sesi ujian siswa** (P0) [mysql] [UI] — Ref: ARCHITECTURE §8b. KP: mulai/lanjut sesi (cek jendela, token, mode perangkat, **kelayakan ADR-016**); timer server; take-over `device_token` (perangkat lama 409); paket soal sekali; jawaban disimpan lokal + batch idempoten + grace 120 detik; tanda ragu-ragu; satu soal per layar. Tes: sambung ulang mempertahankan urutan & sisa waktu; jawaban setelah grace ditolak; siswa tidak layak → pesan netral; dua permintaan mulai bersamaan → satu sesi.
- [ ] **T-1506 Penilaian & penutupan otomatis** (P0) [mysql] — KP: `ExamScorer` (port aturan CBT-Sekolah, bobot dari ujian); job tiap menit menutup sesi lewat waktu/terputus/`force_closed` dan menilai lewat antrean. Tes: port kasus `penilaian_*.test.js`; sesi terputus tetap dinilai.
- [ ] **T-1507 Input nilai essay** (P0) [UI] — KP: guru menilai per soal per siswa (form + impor Excel); status `awaiting_essay` → `final`. Tes: hanya guru pengampu; nilai di luar 0..maks ditolak.
- [ ] **T-1508 Mode perangkat & pelanggaran** (P0) — KP: `lab` layar penuh wajib + kunci setelah batas; `tablet` layar penuh bila didukung; `phone` catat saja (`counted=false`); log append-only. Tes: per mode, hitungan & penguncian sesuai.
- [ ] **T-1509 Monitor pengawas** (P0) [UI] — KP: status per siswa (belum mulai, mengerjakan, terkunci, selesai), progres, pelanggaran, mode perangkat, terakhir terlihat; aksi buka kunci, tambah waktu, paksa selesai (diaudit); polling 5 detik dengan satu kueri agregat. Tes: otorisasi; tanpa N+1; checklist keamanan REUSE_MAP §5.
- [ ] **T-1510 Hasil & ekspor** (P0) [UI] — KP: rekap nilai per ujian/rombel, ekspor Excel (netral formula), hasil ke wali bila `show_result_to_guardian` dan status final. Tes: wali hanya anaknya; nilai non-final tidak tampil.
- [ ] **T-1511 Panduan hari-H** (P1) — KP: `docs/handover/ujian-cbt.md`: persiapan per mode perangkat (lab/SEB, tablet mode kios, HP + Exambro), PIN, token, simulasi putus Wi-Fi, uji beban sebelum hari-H.
