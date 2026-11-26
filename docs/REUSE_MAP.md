# REUSE MAP — Kode Rujukan dari Repo Lain

Semua repo di bawah **hanya-baca**. Jangan mengubahnya, jangan menyalin gaya kodenya mentah-mentah.
Porting = pahami perilaku → tulis ulang idiomatik sesuai ARCHITECTURE.md → buktikan dengan tes yang mendeskripsikan perilaku yang sama.

| Repo                  | Lokasi lokal                                   | Stack                        | Dipakai untuk                                                         |
| --------------------- | ---------------------------------------------- | ---------------------------- | --------------------------------------------------------------------- |
| SIPESAN               | `~/Projects/sipesan`                           | Laravel 13, Blade, Bootstrap | Buku Saku BK (Fase 9), keterlambatan, izin                            |
| PIB Penilaian         | `~/Projects/pib-laravel`                       | Laravel 13, Livewire 4       | Praktik ibadah (Fase 11), pola AuditLogger, impor siswa, tahun ajaran |
| Penilaian Tahfidz     | `~/Projects/penilaian-tahfidz`                 | Laravel 13, Inertia          | Rubrik berversi + mesin nilai setoran (Fase 11)                       |
| standar-ui-project    | `~/Projects/standar-ui-project`                | Dokumen                      | Aturan UI global (semua tugas UI)                                     |
| Aplikasi lama sekolah | `https://amaljayaberkah.id` (tidak ada source) | —                            | Hanya rujukan fitur yang dilihat dari akun wali, lihat §4             |

## 1. SIPESAN → Buku Saku BK

| SIPESAN                                                                                                      | Proyek ini                                                           | Catatan                                                                                        |
| ------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `database/migrations/2026_07_01_000001_create_mts_alihsan_core_tables.php` → `jenis_pelanggaran(nama, poin)` | `point_rules` (category `violation`)                                 | Tambah `code`, `unit_id`, `category`, `is_active`                                              |
| `pelanggaran(siswa_id, tahun_ajaran_id, jenis_pelanggaran_id, tanggal, keterangan)`                          | `behavior_records` + `point_entries`                                 | Poin di-snapshot ke catatan; saldo lewat ledger                                                |
| `2026_08_21_000005_create_tingkat_prestasi_table.php`, `..._000010_create_prestasi_table.php`                | `point_rules` (category `achievement`, `level`) + `behavior_records` | Di SIPESAN prestasi **tanpa poin**; di sini prestasi menghasilkan poin kebaikan                |
| `..._000009_create_keterlambatan_table.php`                                                                  | `attendance_days.status = late` dari scan gerbang                    | Tidak ada input manual keterlambatan; BK membuat catatan dari daftar terlambat (U-03)          |
| `..._000011_create_surat_izin_table.php`                                                                     | `leave_requests`                                                     | Di sini diajukan wali dan disetujui sekolah                                                    |
| `app/Http/Controllers/PelanggaranController.php` (filter nama/kelas/tanggal, export)                         | `Admin/Behavior/BehaviorRecordController` + laporan Fase 13          | Ambil kebutuhan filter & kolom ekspor saja                                                     |
| `app/Support/XlsxExporter.php`                                                                               | **Tidak dipakai**                                                    | Pakai `openspout/openspout`                                                                    |
| `app/Http/Middleware/LegacyAuthenticate.php`                                                                 | **Jangan ditiru**                                                    | Berisi auto-login admin di env `local/testing` — celah keamanan. Peran juga tidak pernah dicek |

## 2. pib-laravel → Praktik Ibadah dan pola umum

| pib-laravel                                                    | Proyek ini                                                    | Catatan                                                                                     |
| -------------------------------------------------------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `app/Domain/Scoring/ScoreCalculator.php` + `tests/Unit/Domain` | `app/Domain/Quran/PibScore.php`                               | Aturan **nilai = 90 − kesalahan**, kesalahan 0–90, kosong = belum dinilai. Port tesnya juga |
| `app/Services/CurriculumService.php`                           | `PibCurriculumService` (bab → subbab → materi, bobot, urutan) | Aturan nama unik per induk, bobot 0,1–100, hapus hanya bila tanpa nilai                     |
| `app/Services/ScoreService.php`                                | `PibAssessmentService`                                        | Satu-satunya penulis nilai PIB                                                              |
| `app/Services/AuditLogger.php`                                 | `app/Services/Audit/AuditLogger.php` (T-105)                  | Pola dan bentuk `before/after`                                                              |
| `app/Services/StudentImporter.php`, `StudentImportResult.php`  | `StudentImportService` (T-207)                                | Batas baris/ukuran, laporan `{imported, failed[{row, error}]}`, transaksi                   |
| `app/Services/AcademicYearService.php`                         | `AcademicYearService` (T-203)                                 | Maks satu aktif dalam transaksi                                                             |
| `docs/AI_RULES.md`, `GEMINI.md`                                | dokumen proyek ini                                            | Gaya dokumen agen dipertahankan                                                             |

## 3. penilaian-tahfidz → Setoran hafalan

| penilaian-tahfidz                                                 | Proyek ini                                                       | Catatan                                                                           |
| ----------------------------------------------------------------- | ---------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `app/Services/ScoreCalculator.php` (mesin nilai rubrik, `bcmath`) | `app/Domain/Quran/RubricScorer.php`                              | Mode nilai langsung & pengurangan, bobot, potongan, ambang, predikat. Port tesnya |
| `app/Services/RubricVersions.php`                                 | `RubricService` (T-1103)                                         | Versi terbit **tidak dapat diubah**; draf → terbit                                |
| Program hafalan (rentang juz/surah/ayat)                          | `memorization_targets.ranges` + `app/Domain/Quran/AyahRange.php` | Validasi rentang terhadap tabel `surahs`                                          |
| `app/Services/AssessmentReports.php`                              | Laporan hafalan (T-1305)                                         | Ambil bentuk rekap saja                                                           |
| Mushaf interaktif (F2, terblokir izin sumber)                     | **Tidak dipakai**                                                | PRD §4.10: hanya daftar surah + jumlah ayat                                       |
| Inertia pages                                                     | **Tidak dipakai langsung**                                       | UI dibangun ulang dengan shadcn sesuai DESIGN.md                                  |

## 4. Aplikasi lama sekolah (dari observasi akun wali, 2026-11-26)

Yang **diambil** sebagai kebutuhan: kartu tagihan keluarga dengan total & "Bayar Sekarang", status tagihan (menunggak/belum jatuh tempo/lunas), jenis tagihan bulanan (SPP per bulan) dan sekali (uang pangkal), metode bayar (tunai, cicilan kasir, Midtrans QRIS/VA, transfer), presensi tap masuk/pulang, izin sakit, Buku Saku BK (pelanggaran & prestasi + poin), mutabaah, saldo e-wallet + limit jajan harian, beranda wali satu layar.

Yang **sengaja tidak ditiru**: halaman admin yang terbuka untuk wali, menu yang menampilkan link "Akses Ditolak", data saldo yang berbeda antar-halaman, Midtrans sandbox di produksi, Tailwind via CDN, label kapital semua dan klaim pemasaran di UI aplikasi.

## 5. CBT-Sekolah → Ujian CBT (ADR-020)

Repo: `ilhamrizqiawan21/CBT-Sekolah` (Express 5 + EJS + MySQL, MTs Al-Ihsan). **Yang diambil adalah aturan dan pelajaran, bukan kode** (bahasa dan framework berbeda).

| CBT-Sekolah                                                        | Proyek ini                                          | Catatan                                                                                                                                                                                       |
| ------------------------------------------------------------------ | --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/PRD.md` §5, `services/penilaianService.js`                   | `App\Domain\Exam\ExamScorer`                        | PG benar = poin; menjodohkan all-or-nothing; essay 0–max dari kertas; nilai = total/maks × 100 dibulatkan. Bobot per ujian, bukan hardcode. Port tes `penilaian_*.test.js` sebagai kasus Pest |
| `services/sesiService.js`, D-007/D-008/D-009                       | `ExamSessionService`, `SessionClock`                | Timer server, take-over `device_token`, sinkron batch + grace 120 detik                                                                                                                       |
| `services/finalizeService.js`                                      | job `CloseExpiredExamSessions` + `ScoreExamSession` | Ditambah: sesi `keluar_paksa` tak ditindaklanjuti tetap dinilai (AUDIT §B.1)                                                                                                                  |
| `services/monitorService.js`, socket.io                            | Halaman monitor, polling 5 detik                    | Tanpa socket.io; WebSocket lewat ADR baru                                                                                                                                                     |
| Kebijakan perangkat (`device_type`, `kebijakan_perangkat.test.js`) | `exams.device_modes` + `exam_sessions.device_type`  | Tiga mode: lab, tablet, phone                                                                                                                                                                 |
| Soal Arab RTL, font lokal (`soal_arab_hp.test.js`)                 | `ArabicText`                                        | Uji visual 360 px                                                                                                                                                                             |
| `docs/AUDIT_KESIAPAN.md` §A                                        | Checklist keamanan T-1509                           | IDOR kunci jawaban, session fixation, XSS teks soal, formula injection ekspor                                                                                                                 |

**Tidak diambil:** EJS/skrip inline, tabel `siswa`/`kelas`/`guru` sendiri (pakai data induk), PIN default, login admin terpisah, hosting laptop sekolah (D-001).
