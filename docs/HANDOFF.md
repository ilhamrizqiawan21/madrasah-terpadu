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

2026-11-27 · Gemini (eksekutor), setelah **Paket P-11 (Fase 9 Buku Saku BK & reward: T-901 s.d. T-906) selesai**

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

## Beranda per peran (Claude, 2026-11-27)

- Berkas milik sesi beranda — agen lain **jangan menyentuh**: `app/Services/Dashboard/*`, `app/Services/Guardian/GuardianHomeSummaryService.php`, `app/Http/Controllers/Admin/DashboardController.php`, `app/Http/Resources/GuardianHomeResource.php`, `resources/js/pages/admin/dashboard.tsx`, `resources/js/pages/guardian/home.tsx`, `resources/js/components/app/dashboard-panel.tsx`, tes `AdminDashboardTest` + `GuardianHomeSectionsTest`. Commit pakai `git add <berkas>`, bukan `git add -A`.
- Data pratinjau lokal (MariaDB, bukan seeder): user `pratinjau.admin` / `pratinjau.guru` / `pratinjau.wali`, rombel MI id > 12, siswa id > 65, 2 ujian, aturan poin berawalan "Pratinjau". Hapus setelah review dengan skrip `preview_cleanup.php` di scratchpad sesi Claude (atau minta Claude menghapusnya).
- Belum diperiksa visual: panel Ujian beranda staf (modul CBT nonaktif di DB lokal; tertutup tes).

## Sedang dikerjakan

Tidak ada (Fase 15 Ujian CBT — Paket **P-16** & **P-17** selesai seluruhnya).

## Langkah berikutnya

1. Lanjut paket berikutnya pada Rilis 1.0 di `docs/TODO.md`: **P-11 Buku Saku BK & reward** (`T-901` s.d. `T-906`), **P-12 Pembiasaan** (`T-1001` s.d. `T-1004`), **P-14 Hafalan & PIB** (`T-1101` s.d. `T-1108`), atau **P-21 Kerangka paritas** (`T-1601` s.d. `T-1606`).
2. Pemilik mengonfirmasi keputusan T-204, T-205, T-206, dan T-211 di bawah.
3. Pemilik: `git push` untuk semua commit sejak `59a0ff1`.

## Arahan pemilik yang berlaku

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
