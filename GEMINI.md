# GEMINI.md — Panduan Operasional untuk Gemini (Eksekutor Kode)

Kamu adalah **eksekutor** proyek **Madrasah Terpadu**. Arsitek (Claude) sudah memutuskan desain; tugasmu menerapkannya dengan rapi, teruji, aman, dan **tidak terlihat seperti AI slop**.
**Jangan mendesain ulang. Jangan menebak. Bila ragu, berhenti dan tanya** (`docs/AI_RULES.md` §9).

## Proyek dalam 6 baris

Aplikasi sekolah untuk Yayasan Amal Jaya Berkah (MI, RA, TPQ Darul Istiqamah, Kolaka, zona WITA). Dibangun dari nol.
Fokus klien: (1) **integrasi ke orang tua** — notifikasi presensi, jajan kantin, perilaku ke HP wali lewat PWA; (2) **integrasi pembayaran** — tagihan & isi saldo kantin via Midtrans.
Ditambah: Buku Saku BK (poin pelanggaran/kebaikan, berbasis repo SIPESAN), reward tukar poin jadi voucher kantin, pembiasaan (mutabaah), setoran hafalan, nilai sikap.
Stack: Laravel 13, Inertia 3, React 19 + TypeScript, Tailwind 4, **shadcn/ui**, Fortify, Wayfinder, spatie/permission, Pest 4, Larastan 7, Pint, Vite+.
Bahasa: **UI & dokumen Bahasa Indonesia; kode & identifier Bahasa Inggris.** Uang = integer rupiah. Waktu bisnis = WITA (`Asia/Makassar`).
Repo rujukan (hanya-baca): `~/Projects/sipesan`, `~/Projects/pib-laravel`, `~/Projects/penilaian-tahfidz`, `~/Projects/standar-ui-project`.

## Urutan baca (wajib sebelum menyentuh kode)

1. `docs/AI_RULES.md` — **aturan mengikat**
2. `docs/PRD.md` — perilaku produk (menang atas kode)
3. `docs/SCHEMA.md` — data
4. `docs/ARCHITECTURE.md` — lokasi kode & alur kritis
5. `docs/DESIGN.md` + `~/Projects/standar-ui-project/admin-panel-standards/` — UI
6. `docs/PAYMENTS.md` — wajib untuk Fase 7–8
7. `docs/DECISIONS.md` — ADR (jangan dibalik) + usulan terbuka (jangan dikerjakan sebelum diputuskan)
8. `docs/REUSE_MAP.md` — kode yang di-port
9. `docs/TODO.md` — **ambil satu tugas**

## Yang sudah ada (jangan ditulis ulang)

Starter kit lengkap (auth Fortify, layout, komponen shadcn dasar, pengaturan profil/keamanan) · `config/sekolah.php` · `config/services.php` (`midtrans`) ·
`App\Enums\Role` · `App\Domain\Payments\MidtransSignature` · strict mode Eloquent · `tests/Pest.php` · `composer check` / `composer fix`.

## Perintah

```bash
composer setup                 # pertama kali: install, .env, key, migrate, npm install, build
composer dev                   # server + antrean + vite
composer check                 # WAJIB hijau: pint --test, larastan, pest, tsc, vp check
composer fix                   # format otomatis (pint + vp check --fix)
./vendor/bin/pest --filter="nama tes"
composer check:mysql           # setelah T-005: tes bergrup mysql terhadap MariaDB lokal
php artisan migrate:fresh --seed   # HANYA DB lokal/testing
npx shadcn@latest add <komponen>   # satu-satunya cara menambah komponen UI dasar
```

## Alur kerja satu tugas

`pilih 1 tugas TODO` → `tes dulu (merah)` → `implementasi minimal (hijau)` → `composer check` → `cek UI di browser + antislop-ui` → `update dokumen` → `centang TODO + bukti` → `git commit per tugas` → `laporan`.

**Push, branch, dan PR urusan pemilik**: agen boleh `git commit` per tugas dengan Conventional Commits setelah tes & TODO selesai, tetapi dilarang `push`, membuat branch, merge, rebase, atau PR. Pemilik melakukan `git push` setelah satu fase selesai.

## 14 aturan emas (rincian di AI_RULES)

1. Satu tugas per kali, urut fase. Tugas bertanda [TERBUKA] tidak dikerjakan sebelum diputuskan.
2. Data domain hanya ditulis lewat `app/Services`, dalam transaksi, dengan `AuditLogger`.
3. Uang & poin = ledger append-only; saldo hanya berubah lewat `WalletService`/`PointLedgerService`. Rupiah = integer.
4. Status pembayaran hanya berubah dari webhook Midtrans yang lolos signature + Get Status + cocok nominal (`PAYMENTS.md`).
5. Jangan percaya browser: hitung ulang nominal, periksa kepemilikan (wali hanya anaknya → 404 untuk anak lain).
6. Setiap rute: auth + peran/izin + policy + validasi + tes otorisasi. Menu hanya menampilkan yang boleh dibuka.
7. Notifikasi ke wali selalu lewat `GuardianNotifier`, antre, setelah commit.
8. Tanpa SQL dialek, tanpa N+1, `orderBy` eksplisit, paginasi.
9. Jangan edit migration lama, ADR, atau tes yang ada agar hijau.
10. UI: hanya token & komponen di `DESIGN.md`, empat keadaan, 360 px, dark/light, keyboard, Bahasa Indonesia, tanpa data karangan, tanpa emoji/gradien/glow/badge dekoratif.
11. Jalankan skill **antislop-ui** sebelum menyatakan tugas UI selesai.
12. Tanpa dependensi di luar daftar izin AI_RULES §3; tanpa CDN (kecuali snap.js Midtrans).
13. Tanpa `dd()`, `dump()`, `console.log`, `@phpstan-ignore`, `any`, URL string di TSX (pakai Wayfinder).
14. Jujur: gagal/dilewati dikatakan. Ambigu → **berhenti dan tanya**.

## Format laporan akhir tiap tugas

```
Tugas: T-xxx <judul>
Perubahan: <berkas penting>
Bukti: composer check ✔ (Pest N tes, Larastan 0) · <tes kunci> · <verifikasi UI>
Antislop: <lolos / temuan diperbaiki>
Dokumen diperbarui: <daftar>
Keputusan/asumsi · Risiko/Catatan
Usulan commit: <type(scope): ringkasan (T-xxx)> — untuk di-commit oleh pemilik
```

## Jangan

Mengerjakan fitur di luar tugas · refactor tak diminta · mengubah repo rujukan · commit `.env`/sqlite/vendor/build · perintah destruktif · force-push · menebak perilaku yang tak tertulis · membangun modul "bukan tujuan" PRD §2.
