# AI RULES — Aturan Mengikat untuk Agen Eksekutor

Berlaku untuk **Gemini** (eksekutor utama) dan agen AI lain yang menyentuh repo ini. Arsitek (Claude) sudah memutuskan desain; tugas eksekutor menerapkannya dengan rapi, teruji, dan aman.
Bila ragu, **berhenti dan tanya** (§9). Menebak lebih mahal daripada bertanya.

## 1. Sumber kebenaran dan urutan baca

Sebelum tugas apa pun, baca berurutan:

1. `GEMINI.md` — ringkasan operasional
2. `docs/PRD.md` — perilaku produk. **Menang** bila bertentangan dengan kode atau TODO
3. `docs/SCHEMA.md` — data
4. `docs/ARCHITECTURE.md` — lokasi kode, alur kritis, keamanan
5. `docs/DESIGN.md` — UI (wajib untuk tugas yang menyentuh tampilan) + `~/Projects/standar-ui-project/admin-panel-standards/`
6. `docs/PAYMENTS.md` — wajib untuk Fase 7–8
7. `docs/DECISIONS.md` — ADR "Diterima" tidak boleh dibalik
8. `docs/REUSE_MAP.md` — kode rujukan dari repo lain
9. Tugas di `docs/TODO.md`

## 2. Alur kerja per paket (wajib, berlaku sejak 2026-11-27)

**Paket** = kumpulan tugas yang tercantum di `TODO.md` bagian "Rencana rilis & paket". Satu putaran agen = satu paket, di jalur yang ditugaskan.

- Kerjakan tugas di dalam paket **berurutan, satu per satu**. Setiap tugas menjalani langkah 3–9 di bawah sampai commit, **baru** tugas berikutnya. Jangan mencampur dua tugas dalam satu commit.
- Paket bertanda **risiko tinggi** (uang, ledger, otorisasi, Midtrans, sesi ujian) dikerjakan Claude atau, bila oleh agen lain, berhenti untuk ditinjau setelah setiap tugas.
- **Berhenti di tengah paket** (laporkan, jangan lanjut) bila: `composer check` merah dua kali berturut-turut pada tugas yang sama · aturan di dokumen ambigu atau bertentangan · tugas bergantung pada **[TERBUKA]** · butuh dependensi baru · perlu mengubah migration/ADR yang ada · menyentuh berkas milik jalur lain di luar aturan berkas bersama.
- **Jalur paralel** bekerja di git worktree + branch yang **dibuat pemilik** (`jalur-a`, `jalur-b`, ...). Agen commit di branch jalurnya saja; merge dilakukan pemilik. Berkas bersama: rute per modul (`routes/<modul>.php`), navigasi per modul, seeder per modul, migration dengan timestamp baru. Perubahan pada berkas bersama yang sudah ada (`routes/web.php`, `HandleInertiaRequests`, `DatabaseSeeder`, `app.css`) dibatasi tambahan satu baris pemanggil dan disebut di laporan.
- **Laporan** dibuat sekali di akhir paket (format di `GEMINI.md`).

Langkah per tugas:

1. Ambil tugas berikutnya di paket. Tugas yang bergantung pada keputusan **[TERBUKA]** dilewati sampai diputuskan.
2. Bekerja di working tree / worktree jalur yang ada. **Jangan** membuat branch sendiri, push, merge, atau rebase — lakukan `git commit` per tugas setelah semua KP lulus dan TODO dicentang; `git push` dan merge dilakukan pemilik (§8).
3. Tulis **tes dulu** dari KP tugas (harus merah karena alasan yang benar).
4. Implementasi minimal sampai hijau.
5. `composer check` (Pint, Larastan 7, Pest, `tsc`, `vp check`) **harus hijau**. Untuk tugas bertanda `[mysql]`: juga `composer check:mysql`.
6. Tugas UI: jalankan `composer dev`, periksa di browser pada 360/768/1280 px, light & dark, keyboard saja, dan empat keadaan. Jalankan skill **antislop-ui** + checklist `DESIGN.md` §9. Sebutkan apa yang diperiksa.
7. Perbarui dokumen terdampak (SCHEMA, ARCHITECTURE, DESIGN, PRD bila perilaku dijelaskan ulang) **di perubahan yang sama**.
8. Centang tugas di TODO.md **hanya** bila semua KP terbukti; tulis bukti singkat (tes, perintah, tanggal).
9. Lakukan `git commit` dengan pesan Conventional Commits Bahasa Inggris (`feat(billing): issue invoices per classroom (T-602)`), lalu sertakan daftar berkas dan ringkasan di laporan akhir. Pemilik melakukan `git push` setelah satu fase selesai.

**Definition of Done**: perilaku sesuai PRD · tes baru ada dan lulus · `composer check` hijau · tanpa N+1 · otorisasi + validasi + audit sesuai §5 · UI lolos §7 · dokumen sinkron · tanpa `dd()`/`dump()`/`console.log`/kode mati/komentar TODO.

## 3. Larangan keras

- ❌ Mengubah aturan bisnis PRD, ADR "Diterima", atau menghapus/melonggarkan tes yang ada agar hijau.
- ❌ Mengedit migration yang sudah di-commit. Perubahan skema = migration baru + update SCHEMA.md + tes.
- ❌ Menulis data domain dari controller, model observer, atau komponen. Hanya lewat `app/Services`.
- ❌ Mengubah status pembayaran atau saldo dari mana pun selain alur di `PAYMENTS.md` dan `WalletService`.
- ❌ `UPDATE`/`DELETE` pada tabel ledger (`*_entries`, `gate_scans`, `payment_notifications`, `audit_logs`, `invoice_adjustments`, `voucher_redemptions`).
- ❌ `float`/`decimal` untuk rupiah. Rupiah = integer.
- ❌ Mempercayai nominal, `student_id`, atau status dari browser tanpa memeriksa kepemilikan dan menghitung ulang di server.
- ❌ SQL khusus dialek, `DB::raw` dengan input pengguna, query di dalam loop, mematikan strict mode.
- ❌ Hapus permanen data pengguna tanpa dialog konfirmasi + audit. Data transaksi tidak pernah dihapus.
- ❌ Menambah dependensi Composer/NPM di luar **daftar izin** ini tanpa ADR:
  `laravel-notification-channels/webpush` (T-401) · `barryvdh/laravel-dompdf` (T-607) · `openspout/openspout` (T-207) · `spatie/laravel-backup` (T-1402) ·
  `@fontsource-variable/plus-jakarta-sans` (T-106) · `qr-scanner` (T-504) · `qrcode` (npm, T-503) · komponen shadcn via `npx shadcn@latest add <nama>` · `@playwright/test` (T-1407).
- ❌ CDN pihak ketiga, kecuali `snap.js` Midtrans di halaman pembayaran.
- ❌ Secret di repo, mencetak `.env`, mematikan CSRF/auth/rate limit, `APP_DEBUG=true` di produksi, `$guarded = []`, `Model::unguard()`.
- ❌ Perintah destruktif tanpa izin: `migrate:fresh`/`db:wipe` selain di DB lokal/testing, `rm -rf` di luar `node_modules`/`vendor`/`storage/framework`/`public/build`, `git reset --hard`, `git push --force`, mengubah riwayat.
- ❌ Fitur di luar tugas ("sekalian"), refactor tak diminta, atau modul yang PRD §2 sebut "bukan tujuan".
- ❌ Data karangan di UI produksi, seeder produksi, atau screenshot: tanpa nama palsu, angka statistik palsu, atau kata `contoh`/`dummy`/`sample`.
- ❌ Mengarang perilaku. PRD diam → lihat REUSE_MAP → masih diam → tanya.

## 4. Standar kode

**PHP**

- `declare(strict_types=1);` di setiap berkas baru. Tipe eksplisit parameter dan return. `final` untuk Service/Domain, `readonly` untuk value object.
- Nama: kelas `PascalCase`, metode `camelCase`, tabel/kolom `snake_case`, rute `kebab-case` dengan nama bertitik (`admin.invoices.index`). Kode berbahasa Inggris; istilah Indonesia hanya di UI, pesan, dan dokumen.
- Validasi lewat Form Request; pesan Indonesia di `lang/id/validation.php`. Hanya `validated()` yang dipakai.
- Enum PHP untuk semua kolom status/tipe (`app/Enums`), dengan `label()` Bahasa Indonesia.
- Exception domain khusus (`InsufficientBalanceException`, `DailyLimitExceededException`, `DuplicateInvoiceException`, ...) dengan pesan Indonesia siap tampil. Dilarang `catch` kosong.
- Komentar menjelaskan **mengapa**. Docblock generik untuk relasi/koleksi agar Larastan lulus. Tanpa `@phpstan-ignore` dan tanpa baseline baru.

**TypeScript/React**

- `strict` TypeScript, tanpa `any` (gunakan tipe dari `resources/js/types`). Komponen fungsi + hooks.
- Rute lewat Wayfinder (`import { index } from '@/actions/...'` / `@/routes/...`). Dilarang string URL.
- Form memakai `useForm`/`<Form>` Inertia; galat dari server ditampilkan inline.
- Komponen UI hanya dari `components/ui` (shadcn) dan `components/app`. Kebutuhan komponen baru: tambah lewat `npx shadcn@latest add`, lalu sesuaikan dengan token.
- Format uang/tanggal hanya lewat `lib/format.ts`. Teks UI Bahasa Indonesia.
- Tanpa state global baru (Redux/Zustand) — state halaman cukup dengan props Inertia + `useState`.

**Tes (Pest)**

- Nama tes berupa kalimat perilaku Bahasa Indonesia. Satu perilaku per tes. Factory untuk data; tanpa seeder besar di tes.
- Setiap rute baru minimal: tamu ditolak, peran salah 403, data milik orang lain 404, validasi gagal, sukses + efek data + audit.
- HTTP eksternal selalu `Http::fake()`; notifikasi `Notification::fake()`; waktu `$this->travelTo()` dalam WITA.

## 5. Keamanan (checklist per tugas yang menyentuh HTTP/data)

- [ ] Rute di grup `auth` + `EnsureActiveUser` + middleware peran/izin; aksi diperiksa policy.
- [ ] Tes: tamu → redirect login; peran salah → 403; wali membuka anak lain → 404.
- [ ] Input divalidasi; tidak ada mass assignment dari `request()->all()`.
- [ ] ID di path/body diverifikasi milik konteks pengguna (tidak ada IDOR).
- [ ] Metode tulis bukan GET; CSRF aktif (kecuali webhook Midtrans).
- [ ] Output Inertia lewat Resource; tidak ada kolom sensitif (`password`, `token_hash`, `two_factor_*`) yang bocor.
- [ ] Unggahan: MIME + ekstensi + ukuran dibatasi, disimpan di disk privat.
- [ ] Rate limit untuk login, scan gerbang (300/menit/petugas), POS, pembuatan order pembayaran (10/menit/wali), webhook.
- [ ] Perubahan penting → `AuditLogger` di transaksi yang sama; audit tanpa password/token.
- [ ] Log tanpa data sensitif.

## 6. Performa dan data

- Paginasi semua daftar; indeks sesuai SCHEMA.md; `orderBy` eksplisit.
- Eager load (`with`), `withCount`, `withSum` alih-alih loop.
- Operasi massal (penerbitan tagihan, impor) dalam chunk + satu transaksi per chunk, dengan ringkasan hasil.
- Kolom waktu disimpan dalam zona aplikasi (`APP_TIMEZONE=Asia/Makassar`, WITA). Laravel tidak mengonversi zona saat menulis atau membandingkan, jadi **jangan** memakai `->utc()` untuk nilai/batas kueri basis data; pakai `App\Support\AppTimezone::name()` bila perlu mengonversi (koreksi 2026-11-28, tes `TimezoneRegressionTest`).

## 7. UI/UX

Ikuti `DESIGN.md` dan standar-ui-project. Wajib: token saja, empat keadaan, breadcrumb (area staf), aksi tabel icon-only dengan tooltip, form di dialog, konfirmasi hapus, dark/light, 360 px, keyboard.
Jalankan skill **antislop-ui** sebelum menyatakan selesai. Jangan menambahkan teks petunjuk yang tidak diminta.

## 8. Git

- **Push, branch, merge, dan PR dilakukan oleh pemilik.** Agen boleh menjalankan `git commit` per tugas (setelah semua KP terbukti dan TODO dicentang), serta `git status`, `git diff`, `git log`. Agen tetap dilarang menjalankan `git push`, `git checkout -b`, `git merge`, `git rebase`, `git stash`, `git reset`, atau membuat PR.
- Jaga perubahan per tugas tetap kecil (≤ ~400 baris diff bila memungkinkan) agar mudah ditinjau dan di-commit pemilik.
- Pastikan berkas yang tidak boleh masuk repo tetap diabaikan: `.env`, `database/*.sqlite`, `storage/*`, `vendor`, `node_modules`, `public/build`, `resources/js/{actions,routes,wayfinder}` (hasil generate).

## 9. Protokol bertanya / eskalasi

Berhenti dan minta keputusan (tulis di `DECISIONS.md` bagian _Usulan terbuka_, lalu sampaikan ke pemilik) bila:

- PRD/ADR/TODO ambigu atau saling bertentangan, atau butuh mengubah skema di luar tugas, aturan bisnis, atau dependensi;
- tes yang ada tampak salah (jangan diubah, laporkan);
- ada risiko kehilangan data, uang, atau poin;
- dokumentasi Midtrans/library berbeda dengan dokumen ini.

Bentuk pertanyaan: konteks (T-xxx), temuan (`berkas:baris`), 2–3 opsi + rekomendasi, dampak.

## 10. Format laporan akhir tiap tugas

```
Tugas: T-xxx <judul>
Perubahan: <3–8 berkas penting>
Bukti: composer check ✔ (Pest N tes, Larastan 0) · <tes kunci> · <verifikasi UI: ukuran layar, tema, keyboard, keadaan>
Antislop: <lolos / temuan yang diperbaiki>
Dokumen diperbarui: <daftar>
Keputusan/asumsi: <bila ada> · Risiko/catatan: <bila ada>
Usulan commit: <type(scope): ringkasan (T-xxx)> — untuk di-commit oleh pemilik
```

Jujur: bila ada yang gagal atau dilewati, tulis apa adanya.
