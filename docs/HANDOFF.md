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

2026-11-27 · Claude (jalur A)

## Keadaan sekarang

- **Fase 0 & 1**: semua tugas `[x]`. CI GitHub hijau sejak ADR-023 (PHP minimum 8.4).
- **Fase 2 (paket P-03)** sedang berjalan: T-201 selesai (commit `dce871b`). Berikutnya T-202.
- `composer check` hijau di commit terakhir (184 tes, Larastan 0).
- Lingkungan lokal: `.env` memakai **MariaDB** `madrasah_terpadu` (sudah `migrate` + `ProductionSeeder`). Tes tetap SQLite in-memory.
- Akun uji lokal: username `admin.uji` (peran Admin, 2FA aktif), dibuat untuk cek UI. Kata sandi dan rahasia TOTP **tidak** dicatat di repo; buat ulang lewat `php artisan tinker` bila perlu (lihat "Cek UI lokal").

## Sedang dikerjakan

Perbaikan temuan Fase 1 dari cek UI, keputusan pemilik 2026-11-27:

1. **Hapus fitur "Hapus akun" sendiri** (`DELETE /settings/profile`, komponen `DeleteUser`, 2 tes bawaan di `tests/Feature/Settings/ProfileUpdateTest.php`). Alasan: ADR-014; fitur ini memungkinkan admin terakhir menghapus diri tanpa audit. Ganti dengan tes "rute 404".
2. **Nonaktifkan passkey** (Fortify feature + tombol di login + menu di Keamanan). Tidak ada di PRD; login hanya username/HP/NIS (T-103).
3. **Terjemahkan teks Inggris starter kit** ke Bahasa Indonesia: `pages/auth/*` (two-factor-challenge, confirm-password, reset-password, forgot-password), `components/{two-factor-setup-modal,manage-two-factor,two-factor-recovery-codes}.tsx`, `pages/settings/security.tsx`. Tanpa huruf kapital semua (DESIGN §2).
4. Catat ketiganya sebagai ADR di `DECISIONS.md` dan bukti di TODO (baris T-101/T-113 atau catatan perbaikan Fase 1).

## Langkah berikutnya

1. Selesaikan "Sedang dikerjakan", commit `fix(auth): ...`.
2. Lanjut P-03 berurutan: T-202 → T-203 → T-204 → T-205 → T-206 → T-207 → T-210 → T-211 → T-212 (lihat TODO "Rencana rilis & paket").

## Arahan pemilik yang berlaku

- **Desain wajib sesuai mockup** di `docs/mockup/menu.html` (SVG per layar, mis. "12 · Pola pengaturan"). Sebelum membangun layar, render mockup terkait:
  ekstrak `<svg aria-label="...">` dari `menu.html`, lalu `magick -density 96 file.svg file.png` di direktori scratchpad. Bila mockup bertentangan dengan keamanan (mis. logo SVG), ikuti keamanan dan sebutkan di laporan.
- Pemilik memantau progres lewat `docs/TODO.md`: centang + bukti segera setelah tiap tugas.
- Deadline klien ketat: kerjakan berurutan, satu tugas satu commit, jangan menunggu laporan akhir paket untuk commit.

## Temuan terbuka (belum ada tugasnya)

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
