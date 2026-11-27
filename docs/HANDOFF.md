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

2026-11-27 · Claude (eksekutor), setelah T-206

## Keadaan sekarang

- **Fase 0 & 1**: semua tugas `[x]`, plus perbaikan temuan cek UI (ADR-024, commit `1cfd189`): hapus akun sendiri dihapus, passkey dimatikan, UI auth/2FA berbahasa Indonesia, tautan & logo Laravel dibuang.
- **2FA tidak wajib lagi** (ADR-025, commit `e475b81`): sakelar `REQUIRE_TWO_FACTOR` (bawaan `false`). Fitur 2FA tetap opsional; akun yang sudah mengaktifkannya tetap ditanya kode.
- **Fase 2 (paket P-03)**: T-201 ✔ (`dce871b`), T-202 ✔ (`f48e553`), T-203 ✔ (`2ee9ff7`), T-204 ✔ (`7c001c4`, Rombel), T-211 ✔ (`f9f1a96`, kunci fitur; di-cherry-pick dari worktree), T-205 ✔ (`d34f185`, Siswa + enrollment), T-206 ✔ (Wali + hubungan anak, commit `feat(guardians): ...`). Pemilih unit/tahun ajaran di topbar dari database (`App\Support\SchoolContext`, T-111 `992ad0d`).
- **Perbaikan setelah review** (`855c151`): `schoolContext` hanya dibagikan ke peran area `/admin` (`Role::adminArea()`), cache pengaturan TTL 10 menit + ditulis ulang setelah commit, pembersihan logo/favicon memakai nilai lama dari baris terkunci, tahun ajaran terpilih di luar 6 terbaru tetap dipakai, `activate` mengunci semua tahun ajaran urut id.
- **Keputusan pemilik diterapkan** (`1e223e7`): `/` dialihkan ke `dashboard` (tamu → login, pengguna → beranda sesuai peran), halaman `welcome` dihapus; semua input jam memakai `TimeInput` 24 jam `HH.MM`.
- `composer check` hijau di commit terakhir (329 tes, Larastan 0). CI GitHub hijau sejak ADR-023 (PHP ≥ 8.4); commit setelah `59a0ff1` belum di-push.
- Lingkungan lokal: `.env` memakai **MariaDB** `madrasah_terpadu` (sudah `migrate` + `ProductionSeeder`). Tes tetap SQLite in-memory.
- Akun uji lokal: username `admin.uji` (Admin, 2FA aktif). Kata sandi dan rahasia TOTP **tidak** dicatat di repo; buat ulang lewat resep "Cek UI lokal".

## Sedang dikerjakan

- Tidak ada.

## Langkah berikutnya

1. Lanjut P-03 berurutan: T-207 (Impor Excel siswa + wali; `GuardianService::create` sudah menautkan berdasarkan nomor HP) → T-210 → T-212.
2. Pemilik mengonfirmasi keputusan T-204, T-205, T-206, dan T-211 di bawah.
3. Pemilik: `git push` untuk commit sejak `59a0ff1`.

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
# admin uji dengan 2FA yang bisa dipakai (rahasia TOTP valid):
php artisan tinker --execute '$g=app(PragmaRX\Google2FA\Google2FA::class); $u=App\Models\User::where("username","admin.uji")->first(); $s=$g->generateSecretKey(); $u->forceFill(["two_factor_secret"=>encrypt($s),"password"=>"<kata-sandi-lokal>"])->save(); echo $g->getCurrentOtp($s);'
```

Periksa 1280 / 768 / 360 px, terang & gelap, keyboard saja, empat keadaan, lalu jalankan skill `antislop-ui`. Bersihkan data uji yang diketik ke DB lokal sebelum selesai.
