# Playbook duplikasi alur aplikasi lama

Berlaku untuk **semua agen** (Claude, Gemini, dan lainnya) sejak 2026-11-28. Tujuannya agar hasil dan cara kerja setiap agen seragam.
Contoh acuan yang sudah jadi: grup **Pengguna** dan **Akademik** (commit `ae728dd` … `ce76041`). Bila ragu, tiru pola di sana.

## 1. Pembagian kerja (arahan pemilik 2026-11-28)

| Agen   | Grup menu                                                                                                                                                                                                        |
| ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Claude | **Keuangan Siswa, Pembayaran online, Akuntansi & Kas, Kantin** (uang, dompet, voucher). Juga keputusan arsitektur.                                                                                               |
| Gemini | Semua grup lain yang masih "Segera" di sidebar: E-Learning, Absensi, Kesiswaan & Asrama, Fasilitas, Pengaduan, Website Sekolah, Laporan non-keuangan, Pengaturan, dst. PPDB **dilewati** sampai pemilik meminta. |

Jangan menyentuh berkas grup milik agen lain kecuali berkas bersama (lihat §6), dan jangan mengerjakan dua grup sekaligus.

## 2. Aturan aplikasi lama: HANYA BACA

Aplikasi lama `amaljayaberkah.id` adalah **produksi dengan data siswa nyata**.

- Boleh: membuka menu lewat tautan sidebar, membaca teks dan atribut DOM (judul, header tabel, label form, opsi select, teks tombol, nilai unik kolom status).
- **Dilarang mengklik tombol aksi** (Simpan, Hapus, Ubah Status, toggle, Kirim, Setujui), termasuk "hanya untuk melihat modal". Pada 2026-11-28 tombol "Ubah Status" di Kelulusan langsung mengubah status siswa tanpa konfirmasi.
- Untuk tahu fungsi sebuah tombol: baca atributnya (`@click`, `wire:click`, `form action`) atau tanya pemilik.
- Jangan menyalin, menyimpan, atau menulis data pribadi yang terlihat (nama, NIS, nomor HP) ke repo, tes, seeder, atau laporan.
- Daftar menu dan path aplikasi lama ada di `docs/legacy/admin-sidebar.md`.

## 3. Prinsip

1. **Alur sama persis, tampilan belum.** Yang ditiru: kolom tabel, filter, isian form, status, tombol aksi, dan urutan langkah. Tampilan modern dipoles di fase terpisah; jangan menghabiskan waktu untuk gaya.
2. **Nama menu ramah**, bukan istilah teknis/AI (mis. "Penerimaan Biaya" → "Keuangan Siswa", "KASIR" → "Kasir Kantin").
3. **Perbaiki kelemahan alur lama yang berbahaya** dan sebutkan di laporan, mis. ubah status tanpa konfirmasi → beri `ConfirmDialog`; bentrok jadwal/booking tidak dicek → tolak dengan pesan jelas.
4. Bila meniru apa adanya akan merusak data (mis. data yang terikat rombel padahal berlaku lintas tahun), pilih desain yang aman, lalu **minta konfirmasi pemilik** di laporan (contoh: Tujuan Pembelajaran dikaitkan ke tingkat kelas).
5. Pemilik memandu menu demi menu. Selesaikan satu menu atau satu tahap, laporkan singkat, lalu tanya "Lanjut?".

## 4. Langkah per menu

1. **Baca menu di aplikasi lama** (§2): judul, kolom, filter, isian form, opsi status, tombol.
2. **Cek kode yang ada** di repo agar tidak menduplikasi (model, service, komponen). Jangan menulis ulang yang sudah ada.
3. **Backend**
    - Migration baru `database/migrations/2026_11_28_0000NN_*.php` (nomor lanjut dari yang terakhir; jangan edit migration lama).
    - Model dengan atribut `#[Fillable([...])]`, docblock `@property`, `casts()`, relasi bertipe.
    - Service di `app/Services/<Domain>/` dalam `DB::transaction` + `AuditLogger::record(...)`. Aturan bisnis (bentrok, duplikat, status) dicek di service dan dilempar sebagai `ValidationException`.
    - Controller tipis: validasi (`$request->validate` dengan nama atribut Bahasa Indonesia), panggil service, `Inertia::flash('toast', ...)`, `back()`.
    - Rute di berkas grup (`routes/academic.php` untuk Akademik, buat `routes/<grup>.php` bila belum ada lalu `require` di `routes/web.php`), dengan `permission:` dan, bila ada, `feature:`.
    - Kepemilikan dicek di server: data milik orang lain → `abort_unless(..., 404)`.
4. **Menu**: di `app/Support/Navigation.php` ganti `self::soon('Label', 'slug', ...)` menjadi `['label' => ..., 'route' => ..., 'permission' => ...]`. Izin boleh beberapa dipisah `|` (cukup salah satu). Setelah itu perbarui `tests/Feature/Navigation/ComingSoonTest.php` agar memakai modul lain yang masih "Segera".
5. **Izin baru** (bila perlu): tambah case di `App\Enums\Permission` + `label()`, baris di `App\Support\PermissionCatalog`, dan pemberian ke peran di `RolesAndPermissionsSeeder`. Jalankan `php artisan db:seed --class=RolesAndPermissionsSeeder --force` di lokal.
6. **Wayfinder**: selalu `php artisan wayfinder:generate --with-form` (tanpa `--with-form` tsc rusak).
7. **Frontend** (`resources/js/pages/admin/<grup>/...`)
    - Master sederhana (daftar + filter + tambah/ubah/hapus): pakai `MasterCrudPage` (`components/app/master-crud-page.tsx`), field `text | number | time | select | textarea`.
    - Halaman lain: `StaffLayout`, `PageHeader`, `DataTable`, `FormDialog`, `ConfirmDialog`, `StatusBadge`, `EmptyState`, `@container` + kelas `@3xl:` untuk kolom sekunder.
    - Jam pakai `TimeInput` (24 jam HH.MM), tanggal pakai `DateInput`/`DateText`. **Jangan** `type="date"`/`type="time"`.
    - Tombol berwarna sesuai arahan pemilik: aksi utama `default`, cetak/aksi sekunder penting `soft`, peringatan `warning`, hapus `outline` + `text-destructive` dengan konfirmasi.
    - **Layout halaman wajib fungsi panah**: `Page.layout = (page: ReactNode) => page;` (atau `masterLayout`). Deklarasi `function` membuat halaman blank (React error #31).
    - URL hanya dari Wayfinder (`@/actions/...`), bukan string.
8. **Tes** di `tests/Feature/Admin/<Nama>Test.php` (Pest): alur sukses, validasi/aturan bisnis utama, otorisasi (peran lain 403/404), props Inertia (`assertInertia`). Ringkas: 1–3 tes per menu.
9. **Data dummy lokal** bila menu kosong tidak bisa dinilai: tambahkan ke `Database\Seeders\LegacyMenusDemoSeeder` (idempoten `firstOrCreate`, menolak jalan di produksi). Jangan memakai data asli dari aplikasi lama.
10. **Verifikasi** (urutan ini, cukup yang relevan):
    ```bash
    ./vendor/bin/pint
    npx vp check --fix
    npm run types:check
    npm run build                     # wajib sebelum tes halaman baru (manifest Vite)
    php artisan migrate --force       # DB lokal
    ./vendor/bin/pest tests/Feature/Admin/<Nama>Test.php tests/Feature/Navigation
    ./vendor/bin/phpstan analyse app --memory-limit=1G
    ```
    Lalu **buka halamannya di browser** (`http://localhost:8000/...`): pastikan tidak blank, tidak ada error konsol, dialog form terbuka. Tes tidak menangkap error render. Bila tidak punya akses browser, minta pemilik membuka halamannya sebelum menyatakan selesai.
    `composer check` penuh **hanya bila pemilik meminta** (arahan 2026-11-28); cek terarah di atas tetap wajib.
11. **Commit per menu/tahap**, Conventional Commits Bahasa Inggris, `git add` berkas yang disentuh saja (jangan `git add -A`). Jangan push/branch.
12. **Laporan singkat ke pemilik** (Bahasa Indonesia, tanpa jargon): apa yang dibuat sesuai alur lama, perbedaan yang disengaja + alasannya, bukti (jumlah tes lulus, Larastan 0, tsc, dicek di browser), yang perlu keputusan pemilik, lalu "Lanjut?".
13. **Perbarui `docs/HANDOFF.md`** bagian "Duplikasi alur aplikasi lama" setelah tiap grup selesai.

## 5. Aturan teknis yang sering terlewat

- Waktu disimpan dalam zona aplikasi WITA (`APP_TIMEZONE=Asia/Makassar`). **Jangan** `->utc()` untuk batas query atau nilai; pakai `App\Support\AppTimezone::name()` untuk "hari ini".
- `orWhere` di dalam `whereHas`/pencarian harus dibungkus `where(fn ...)` agar tidak membocorkan baris lain.
- Berkas unggahan di disk privat `local`, diunduh lewat rute yang memeriksa hak akses; batasi `mimes` dan `max`.
- Larastan kadang crash (`zend_mm_heap`/SIGABRT) → ulangi saja. `npx vp check --fix` kadang segfault → ulangi.
- Tes satu berkas + foldernya sekaligus memicu galat Pest "folder already uses the test case"; jalankan salah satu.
- Uang: integer rupiah; saldo dan poin hanya lewat service ledger (`WalletService`, `PointLedgerService`). Modul uang milik Claude (§1).

## 6. Berkas bersama (sentuh seperlunya, commit terpisah yang jelas)

`app/Support/Navigation.php`, `tests/Feature/Navigation/ComingSoonTest.php`, `app/Enums/Permission.php`, `app/Support/PermissionCatalog.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `database/seeders/LegacyMenusDemoSeeder.php`, `resources/js/components/app/*`, `resources/js/layouts/staff-layout.tsx`, `routes/web.php`.
Sebelum mengubah: `git pull` tidak dipakai (tanpa branch), jadi cek `git status`/`git diff` agar tidak menimpa perubahan agen lain yang belum di-commit.

### Bekerja bersamaan di folder yang sama (tanpa branch)

- **Nomor migration dipisah**: Gemini memakai `2026_11_28_0000NN` (lanjut dari `000030`); Claude memakai `2026_11_28_1000NN`. Jangan memakai nomor milik agen lain.
- **Stage per bagian, bukan per berkas**, untuk berkas bersama: sebelum commit jalankan `git diff --cached` dan pastikan tidak ada baris milik agen lain (mis. item menu yang rutenya belum di-commit). Bila terbawa, keluarkan dari index tanpa mengubah berkas kerja (`git restore --staged <berkas>`), lalu stage ulang hanya bagian sendiri.
- Jangan memakai perintah git yang membuang perubahan berkas kerja (stash, checkout berkas, reset keras) dan jangan menjalankan formatter ke seluruh repo: itu bisa menghapus pekerjaan agen lain yang belum di-commit.
- Tes navigasi bisa gagal karena halaman agen lain belum ada di build (`Unable to locate file in Vite manifest`). Jalankan `npm run build` ulang; bila tetap gagal karena berkas agen lain, catat di laporan, jangan diperbaiki.
