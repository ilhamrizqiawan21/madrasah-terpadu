# Audit Keamanan — Madrasah Terpadu (`T-1401`)

Tanggal audit: 2026-11-27  
Acuan: `docs/AI_RULES.md` §5 · Hasil `php artisan route:list --json` (227 rute) · `composer audit` · `npm audit --omit=dev`

## 1. Ringkasan Audit Dependensi

| Alat       | Perintah                          | Hasil                                                      |
| ---------- | --------------------------------- | ---------------------------------------------------------- |
| Composer   | `composer audit --no-interaction` | ✔ `No security vulnerability advisories found.` (0 temuan) |
| NPM (prod) | `npm audit --omit=dev`            | ✔ `found 0 vulnerabilities` (0 temuan)                     |

## 2. Pemetaan Rute × Middleware

### A. Rute Publik / Non-`auth` yang Disengaja

- `GET /` — redirect ke `dashboard` (tamu ke login).
- `GET /up` — healthcheck bawaan Laravel.
- `GET /manifest.webmanifest`, `GET /offline`, `GET /sw.js` — aset PWA publik (tanpa data pengguna).
- `GET /branding/{asset}` — aset logo/favicon publik sekolah (validasi whitelist asset).
- `GET|POST /ujian/masuk` — halaman & login siswa CBT (`feature:cbt`, guard `student` terpisah, rate limit per NIS).
- `GET /storage/{path}` — penyajian berkas disk publik; berkas privat (foto siswa, lampiran izin, lampiran soal) disimpan di disk `local` dan hanya disajikan lewat controller berotorisasi.

### B. Rute Portal Wali (`/wali/*`, 20 rute)

Seluruh rute berada di grup `['web', 'auth:web', 'role:wali']`:

- **11 rute `/wali/anak/{student}/*`**: dilindungi middleware `guardian.owns-student` (404 untuk anak yang bukan milik wali).
- `GET /wali/anak/{student}/izin/{leaveRequest}/lampiran`: dilindungi `guardian.owns-student` + validasi `$leaveRequest->student_id === $student->id` (404 bila silang).
- `POST /wali/anak` (`guardian.child.update`): memvalidasi kepemilikan via `GuardianContext::select()`, mengembalikan 404 untuk siswa lain.
- `PATCH /wali/notifikasi/{notification}/baca`: query di-scope ke `$user->notifications()->whereKey(...)` (404 untuk notifikasi pengguna lain).

### C. Rute Area Staf, Gerbang, Kantin, & CBT

- Seluruh rute `/admin/*` berada di grup `['web', 'auth:web', 'role:admin|bendahara|guru|bk|kepala_unit']` dan mayoritas menggunakan middleware `permission:<izin>` + `feature:<modul>` + pengecekan policy/ownership di service/controller.
- Rute kiosk `/gerbang` & `/gerbang/scan`: `role:petugas_gerbang|admin`, `feature:attendance`, `throttle:300,1`.
- Rute kiosk `/kantin`: `role:kasir_kantin|bendahara|admin`.
- Rute `/ujian/*` (selain `/ujian/masuk`): `auth:student`, `feature:cbt`.

## 3. Checklist `AI_RULES.md` §5

- [x] Grup `auth` + `EnsureActiveUser` + middleware peran/izin aktif di rute aplikasi.
- [x] Uji IDOR menyeluruh rute `/wali/*`: `tests/Feature/Security/GuardianIdorAuditTest.php` (tamu → redirect login, non-wali → 403, wali mengakses anak/izin/notifikasi wali lain → 404).
- [x] Tidak ada `request()->all()` untuk mass assignment; seluruh mutasi domain tervalidasi dan berjalan di transaksi `app/Services/*`.
- [x] Metode tulis memakai `POST`/`PUT`/`PATCH`/`DELETE` dengan proteksi CSRF aktif.
- [x] Kolom sensitif (`password`, `token_hash`, `two_factor_*`) tidak diekspos di Inertia props maupun `audit_logs`.
- [x] Unggahan foto/lampiran/CSV/gambar CBT divalidasi MIME/ekstensi/ukuran dan disimpan di disk privat `local`.
- [x] Rate limit aktif pada login staf, login CBT, dan `gerbang/scan` (`300/menit`).

## 4. Catatan untuk Rilis Peralihan (Modul Uang)

- Rute webhook Midtrans, pembuatan order pembayaran (`throttle:10,1`), dan POS kantin akan diaudit ulang pada paket **P-08 / P-09b / P-10** saat modul pembayaran dan dompet diaktifkan.
