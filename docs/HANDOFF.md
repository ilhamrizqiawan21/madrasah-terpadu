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

2026-11-27 · Claude (eksekutor), setelah T-204

## Keadaan sekarang

- **Fase 0 & 1**: semua tugas `[x]`, plus perbaikan temuan cek UI (ADR-024, commit `1cfd189`): hapus akun sendiri dihapus, passkey dimatikan, UI auth/2FA berbahasa Indonesia, tautan & logo Laravel dibuang.
- **2FA tidak wajib lagi** (ADR-025, commit `e475b81`): sakelar `REQUIRE_TWO_FACTOR` (bawaan `false`). Fitur 2FA tetap opsional; akun yang sudah mengaktifkannya tetap ditanya kode.
- **Fase 2 (paket P-03)**: T-201 ✔ (`dce871b`, termasuk Pengaturan > Tampilan untuk logo/favicon), T-202 ✔ (Unit & jam presensi, Pengaturan), T-203 ✔ (Tahun ajaran, Data Induk, pola master mockup 01), T-204 ✔ (Rombel di Data Induk: CRUD per unit + tahun ajaran, wali kelas guru aktif, salin dari tahun lain, nonaktifkan bukan hapus; tahun ajaran yang dipakai rombel tidak bisa dihapus). Pemilih unit/tahun ajaran di topbar sudah dari database (`App\Support\SchoolContext`) dan menjadi bawaan saringan Rombel. **Berikutnya T-205.**
- `composer check` hijau di commit terakhir (249 tes, Larastan 0). CI GitHub hijau sejak ADR-023 (PHP ≥ 8.4); commit setelah `59a0ff1` belum di-push.
- Lingkungan lokal: `.env` memakai **MariaDB** `madrasah_terpadu` (sudah `migrate` + `ProductionSeeder`). Tes tetap SQLite in-memory.
- Akun uji lokal: username `admin.uji` (Admin, 2FA aktif). Kata sandi dan rahasia TOTP **tidak** dicatat di repo; buat ulang lewat resep "Cek UI lokal".

## Sedang dikerjakan

Tidak ada (semua perubahan sudah di-commit).

## Langkah berikutnya

1. Lanjut P-03 berurutan: T-205 → T-206 → T-207 → T-210 → T-211 → T-212 (lihat TODO "Rencana rilis & paket").
   Siswa (T-205) memakai pola mockup 01 seperti `pages/admin/classrooms/index.tsx` (saringan unit/tahun ajaran dari `SchoolContext`, cari, paginasi 25).
   Enrollment menunjuk `classrooms`; pilihan rombel sebaiknya hanya rombel aktif (`classrooms.is_active`).
2. Pemilik: `git push` untuk commit sejak `59a0ff1`.

## Arahan pemilik yang berlaku

- **Desain wajib sesuai mockup** di `docs/mockup/menu.html` (SVG per layar, mis. "12 · Pola pengaturan"). Sebelum membangun layar, render mockup terkait:
  ekstrak `<svg aria-label="...">` dari `menu.html`, lalu `magick -density 96 file.svg file.png` di direktori scratchpad. Bila mockup bertentangan dengan keamanan (mis. logo SVG), ikuti keamanan dan sebutkan di laporan.
- Pemilik memantau progres lewat `docs/TODO.md`: centang + bukti segera setelah tiap tugas.
- Deadline klien ketat: kerjakan berurutan, satu tugas satu commit, jangan menunggu laporan akhir paket untuk commit.

## Temuan terbuka (belum ada tugasnya)

- ~~Input jam AM/PM~~ selesai: semua input jam memakai `TimeInput` (24 jam, `HH.MM`).
- **Halaman `/` masih halaman sambutan Laravel** (`resources/js/pages/welcome.tsx`, rute `home`). Butuh keputusan pemilik: alihkan ke login/beranda, atau halaman depan sekolah. Dua tes memakai `route('home')` dengan status 200 (`ExampleTest`, `SecurityMiddlewareTest`).
- Sisa starter yang tidak terpakai: `components/app-header.tsx` + `layouts/app/app-header-layout.tsx` (tidak dirender di mana pun).
- Halaman **Pengguna staf** belum memakai `SettingsShell`, padahal ada di sub-navigasi Pengaturan (mockup menaruhnya di sana sebagai "Pengguna & peran").
- Topbar staf masih memakai inisial "MT", belum logo unggahan (masuk sisa T-208).
- `npx shadcn@latest add` gagal karena mencoba `pnpm`; proyek memakai npm. Komponen `textarea` belum ada.
- `composer check:mysql` lokal: `root` MariaDB memakai `unix_socket`. Jalankan dengan `DB_USERNAME`/`DB_PASSWORD` user khusus ber-DB `madrasah_terpadu_testing`.

## Cek UI lokal (resep singkat)

```bash
npm run build && php artisan serve --host=127.0.0.1 --port=8000
# admin uji dengan 2FA yang bisa dipakai (rahasia TOTP valid):
php artisan tinker --execute '$g=app(PragmaRX\Google2FA\Google2FA::class); $u=App\Models\User::where("username","admin.uji")->first(); $s=$g->generateSecretKey(); $u->forceFill(["two_factor_secret"=>encrypt($s),"password"=>"<kata-sandi-lokal>"])->save(); echo $g->getCurrentOtp($s);'
```

Periksa 1280 / 768 / 360 px, terang & gelap, keyboard saja, empat keadaan, lalu jalankan skill `antislop-ui`. Bersihkan data uji yang diketik ke DB lokal sebelum selesai.
