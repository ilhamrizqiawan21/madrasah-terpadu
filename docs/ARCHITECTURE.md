# ARCHITECTURE — Struktur Kode, Alur, dan Keamanan

Dokumen ini menjelaskan **di mana kode ditaruh** dan **bagaimana alur kritis bekerja**. Keputusan besar dan alasannya ada di `DECISIONS.md`.
Tanda ✅ = sudah ada di fondasi (Fase 0).

## 1. Stack

| Lapisan      | Pilihan                                                          | Catatan                                                                        |
| ------------ | ---------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| Runtime      | PHP 8.4+ (lokal 8.5), Node 24+                                   | Produksi: ekstensi `bcmath`, `intl`, `gd`, `zip`, `pdo_mysql`                  |
| Backend      | Laravel 13 ✅                                                    | Starter kit resmi React (`laravel/react-starter-kit`, cabang `main`)           |
| Auth         | Laravel Fortify ✅                                               | Login, reset, 2FA, passkey. Registrasi publik **dimatikan** di T-101           |
| Bridge       | Inertia 3 ✅                                                     | Tanpa REST API terpisah untuk UI. JSON hanya untuk webhook, scan, POS, push    |
| Frontend     | React 19 + TypeScript strict ✅                                  | `resources/js`                                                                 |
| UI kit       | shadcn/ui (Radix) + Tailwind CSS 4 ✅                            | `resources/js/components/ui` milik kita, boleh diubah                          |
| Rute bertipe | Laravel Wayfinder ✅                                             | Panggil rute lewat fungsi hasil generate. **Dilarang** URL string di TSX       |
| Build        | Vite+ (`vp`) ✅                                                  | `npm run build`, `npm run check`, `npm run types:check`                        |
| Database     | MySQL 8 / MariaDB 10.11+                                         | Tes default SQLite in-memory; tes uang & konkurensi juga di MariaDB (T-005)    |
| Antrean      | Queue driver `database`                                          | Worker via Supervisor. Redis tidak wajib                                       |
| Penjadwal    | Laravel Scheduler (cron per menit)                               | Alpa harian, kedaluwarsa order, rekonsiliasi, verifikasi saldo                 |
| Hak akses    | spatie/laravel-permission ✅                                     | Nama peran dari `App\Enums\Role` ✅                                            |
| Notifikasi   | Notifications `database` + Web Push                              | `laravel-notification-channels/webpush` (T-401)                                |
| Pembayaran   | Midtrans Snap via Laravel HTTP Client                            | Tanpa SDK `midtrans-php` (ADR-007). `App\Domain\Payments\MidtransSignature` ✅ |
| PDF / Excel  | `barryvdh/laravel-dompdf`, `openspout/openspout`                 | Dipasang pada tugas pertama yang membutuhkannya                                |
| Backup       | `spatie/laravel-backup`                                          | T-1402                                                                         |
| Mutu         | Pest 4 ✅, Larastan level 7 ✅, Pint ✅, `vp check` ✅, `tsc` ✅ | `composer check` harus hijau                                                   |

Dependensi di luar tabel ini dan di luar daftar izin `AI_RULES.md` §3 **dilarang** tanpa ADR baru.

## 2. Struktur folder

```
app/
├── Domain/                 # Aturan bisnis murni (tanpa HTTP/DB bila mungkin), diuji dengan unit test
│   ├── Attendance/         # AttendanceClassifier
│   ├── Billing/            # BillingPeriod, InvoiceStatusResolver
│   ├── Ledger/             # BalanceCalculator
│   ├── Payments/           # MidtransSignature ✅, MidtransStatusMapper
│   ├── Points/             # ThresholdEvaluator, AttitudeScoreCalculator
│   ├── Exam/               # ExamScorer, ExamEligibilityResolver, ShuffleOrder, SessionClock
│   └── Quran/              # AyahRange, RubricScorer, PibScore
├── Enums/                  # Role ✅, AttendanceStatus, InvoiceStatus, WalletEntryType, PointAccount, ...
├── Models/                 # Relasi, cast, scope. Tanpa logika bisnis
├── Services/               # SATU-SATUNYA penulis data domain (transaksi + audit)
│   ├── Attendance/         # GateScanService, LeaveRequestService, DailyAttendanceService
│   ├── Billing/            # FeeTypeService, BillingRunService, InvoiceAdjustmentService, ManualPaymentService
│   ├── Payments/           # MidtransClient, PaymentOrderService, MidtransNotificationProcessor
│   ├── Wallet/             # WalletService, VoucherService
│   ├── Behavior/           # BehaviorRecordService, PointLedgerService, ThresholdService, RewardService
│   ├── Habits/             # HabitLogService
│   ├── Quran/              # MemorizationService, PibAssessmentService
│   ├── Attitude/           # AttitudeAssessmentService
│   ├── Notifications/      # GuardianNotifier
│   ├── Users/              # StaffUserService (akun staf, penjaga admin aktif terakhir)
│   └── Audit/              # AuditLogger
├── Http/
│   ├── Controllers/{Admin,Guardian,Gate,Canteen,Webhooks}/
│   ├── Requests/           # Form Request per aksi tulis
│   ├── Middleware/         # EnsureActiveUser, EnsureGuardianOwnsStudent, SecurityHeaders
│   └── Resources/          # Bentuk data ke Inertia (jangan kirim model mentah)
├── Notifications/          # StudentCheckedIn, CanteenPurchaseMade, BehaviorRecorded, InvoicePaid, ...
├── Policies/
├── Jobs/                   # ProcessMidtransNotification, MarkDailyAbsences, ExpirePaymentOrders
└── Support/                # Money (format rupiah), tanggal WITA

resources/js/
├── components/ui/          # shadcn
├── components/app/         # DataTable, PageHeader, MoneyText, StudentPicker, EmptyState, ConfirmDialog, FormDialog
├── layouts/                # staff-layout, guardian-layout, auth-layout, kiosk-layout
├── pages/{admin/<modul>, guardian, gate, canteen}/
├── lib/                    # format.ts, push.ts, scanner.ts
└── types/

public/sw.js                # push + halaman offline. TIDAK meng-cache halaman berisi data
public/manifest.webmanifest
```

## 3. Aturan lapisan

1. **Controller**: `authorize` (policy) → `validated()` → Service → `Inertia::render` / redirect + flash. Tanpa query rumit, tanpa aturan bisnis.
2. **Service**: satu-satunya penulis data domain. Tulisan multi-tabel dalam `DB::transaction()`, `AuditLogger` di transaksi yang sama.
3. **Domain**: perhitungan murni, diuji tanpa database.
4. **Model** tidak memanggil Service. Dilarang observer yang menulis data domain.
5. **Notifikasi** dikirim **setelah commit** (`ShouldQueueAfterCommit`): wali tidak pernah menerima notifikasi untuk data yang di-rollback.
6. **Resources** menentukan field yang sampai ke browser.

## 4. Area aplikasi dan routing

| Area     | Prefix                      | Layout                                                                    | Akses                                                 |
| -------- | --------------------------- | ------------------------------------------------------------------------- | ----------------------------------------------------- |
| Staf     | `/admin/...`                | `staff-layout`: topbar, grup menu rata tengah di desktop, sheet di ponsel | peran staf sesuai menu                                |
| Wali     | `/wali/...`                 | `guardian-layout`: mobile-first, navigasi bawah 4 item                    | `wali` + `EnsureGuardianOwnsStudent`                  |
| Gerbang  | `/gerbang`                  | `kiosk-layout`                                                            | `petugas_gerbang`, `admin`                            |
| Kantin   | `/kantin`                   | `kiosk-layout`                                                            | `kasir_kantin`, `bendahara`, `admin`                  |
| Webhook  | `/webhooks/midtrans`        | —                                                                         | publik, signature wajib, `throttle:120,1`, tanpa CSRF |
| Branding | `/branding/{logo\|favicon}` | —                                                                         | publik (dibutuhkan halaman login), hanya dua aset itu |
| Push     | `/push/subscriptions`       | —                                                                         | pengguna login                                        |
| Ujian    | `/ujian/...`                | `exam-layout`: layar penuh, tanpa menu, satu soal per layar               | guard `student` (NIS + PIN), hanya sesi ujiannya      |

`/dashboard` mengarahkan sesuai peran: wali → `/wali`, petugas gerbang → `/gerbang`, kasir → `/kantin`, lainnya → `/admin`.
Menu dibangun **di server** dari izin pengguna (`HandleInertiaRequests` membagikan `navigation`): menu tidak pernah menampilkan rute yang akan ditolak.

## 5. Alur kritis: notifikasi ke wali

```
Service (commit) ─► GuardianNotifier ─► Notification (ShouldQueueAfterCommit, antrean "notifications")
                                          ├─ database → kotak masuk wali (selalu)
                                          └─ WebPush  → semua subscription wali, bila kategori diizinkan
Worker ─► push service ─► 404/410 → hapus subscription itu
```

- Semua wali yang terhubung ke siswa menerima notifikasi.
- Preferensi kategori dicek di `via()`. `billing` dan `account` selalu aktif (`config/sekolah.php` ✅).
- Payload push: judul, isi singkat, URL tujuan, `tag` (sejenis tidak menumpuk). Tanpa data sensitif.
- Kegagalan push tidak menggagalkan transaksi asal (retry 3x dengan backoff).

## 6. Alur kritis: pembayaran Midtrans

Rincian, status, dan skenario uji: `PAYMENTS.md`. Ringkasnya:

1. Wali memilih tagihan atau nominal top-up → `PaymentOrderService::create()` membuat `payment_orders` (`pending`, `order_id` unik, `gross_amount` dihitung dari **database**) + item → minta Snap token.
2. Browser membuka Snap. Callback browser **hanya** mengarahkan ke halaman "menunggu konfirmasi".
3. Midtrans memanggil `/webhooks/midtrans` → simpan `payment_notifications` mentah → verifikasi `MidtransSignature` → dispatch `ProcessMidtransNotification` → respons 200 cepat.
4. Job: `lockForUpdate` order → **Get Status API** → cocokkan `gross_amount` → petakan status → bila lunas dan belum `paid`: buat pembayaran + alokasi tagihan, atau entri top-up → audit → notifikasi. Sudah diproses: abaikan.
5. Scheduler: order `pending` lewat batas waktu dicek lalu `expired`. Rekonsiliasi harian membandingkan order kemarin dengan Midtrans.

## 7. Alur kritis: ledger uang dan poin

- **Dompet**: `wallet_entries` append-only. Saldo = jumlah `amount`. `wallets.balance` adalah **cache** yang diperbarui di transaksi yang sama dengan `lockForUpdate` pada baris `wallets`, dicocokkan harian oleh `wallet:verify-balances`.
- **Voucher**: dipakai sebelum saldo uang. `canteen_vouchers.remaining` berkurang lewat `voucher_redemptions` dalam transaksi pembelian yang sama.
- **Poin**: `point_entries` append-only per siswa + tahun ajaran + akun (`violation`/`merit`). Pembatalan = entri pembalik.
- Setiap entri uang/poin punya `idempotency_key` unik (`purchase:<uuid dari POS>`, `midtrans:<order_id>`, `behavior:<id>`).

## 8. Alur kritis: scan gerbang

1. `/gerbang` selalu fokus pada satu input. Scanner barcode mengetik token + Enter; kamera memakai `BarcodeDetector`, fallback `qr-scanner` (T-504).
2. `POST /gerbang/scan {token}` → `GateScanService::scan()` → kartu aktif (token di-hash) → `AttendanceClassifier` → `gate_scans` + `attendance_days` → notifikasi afterCommit.
3. Respons JSON singkat (nama, foto kecil, rombel, hasil). Target ≤ 300 ms di server.
4. Mode offline tidak didukung fase ini; layar menampilkan status koneksi.

## 8b. Alur kritis: sesi ujian CBT (ADR-020)

1. Siswa masuk (guard `student`, NIS + PIN, rate limit per NIS) → pilih ujian yang terbuka untuk rombelnya.
2. `ExamSessionService::start()` dalam transaksi + lock: cek jendela waktu, token, mode perangkat, **kelayakan ADR-016** (`ExamEligibilityResolver`), buat/lanjutkan sesi. Sesi yang ada → ambil alih: `device_token` baru, perangkat lama ditolak (409), dicatat sebagai `takeover`.
3. Paket soal dikirim sekali tanpa `answer_key`; urutan dari `shuffle_seed`.
4. Jawaban dikirim batch (`client_ts`), upsert idempoten; diterima bila `client_ts ≤ deadline` dan diterima ≤ 120 detik setelah deadline.
5. Selesai oleh siswa, admin, atau job `CloseExpiredExamSessions` (tiap menit) → `ScoreExamSession` di antrean → `exam_results`.
6. Monitor admin: polling 5 detik, satu kueri agregat per ujian. WebSocket hanya lewat ADR baru.

## 9. Keamanan

- **Autentikasi**: Fortify; login dengan username / no. HP / NIS; rate limit 5/menit per identitas+IP; sesi diregenerasi; `EnsureActiveUser`; 2FA wajib untuk `admin` dan `bendahara` (T-104).
- **Otorisasi**: middleware peran/izin **dan** policy di setiap rute. Tes per rute: tamu → login, peran salah → 403, wali membuka anak lain → 404.
- **IDOR wali**: semua query portal wali berawal dari `$request->user()->guardian->students()`.
- **CSRF**: aktif kecuali `/webhooks/midtrans` (dilindungi signature + Get Status API).
- **Header** (`SecurityHeaders`): CSP kompatibel Vite & domain Snap Midtrans, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` kamera hanya untuk `/gerbang` dan `/kantin`.
- **Berkas**: disk privat; akses lewat rute ber-policy atau `temporarySignedRoute` 5 menit; MIME/ekstensi/ukuran ≤ 2 MB divalidasi; gambar dikompres.
  Pengecualian: logo & favicon (T-201) disajikan publik lewat `BrandingAssetController` (`/branding/{logo|favicon}?v=<hash>`, `Cache-Control` 1 hari, `nosniff`); SVG ditolak saat unggah karena bisa memuat skrip.
- **Token kartu QR**: 32 byte acak, disimpan sebagai hash SHA-256; dapat dicabut.
- **Secret** hanya di `.env`. `APP_DEBUG=false` di produksi. Log tanpa token, password, payload pembayaran lengkap, nomor HP utuh.
- **Audit**: `audit_logs` untuk data induk, keuangan, poin, nilai, peran, pengaturan, dispensasi ujian, koreksi kunci soal.
- **Ujian**: guard `student` terpisah dari `web`; sesi siswa hanya berlaku di `/ujian`; kunci jawaban tidak pernah ada di props Inertia siswa (dites); ekspor Excel menetralkan awalan `= + - @`.

## 10. Performa

- Strict mode Eloquent di luar produksi ✅.
- Paginasi server-side (25/50/100) + indeks pada kolom filter.
- Beranda wali: satu query per kartu, cache 60 detik per anak, diinvalidasi Service terkait.
- Inertia: `Inertia::defer()` untuk props berat; partial reload untuk filter tabel.
- Code-splitting per halaman, font self-hosted, gambar ber-ukuran.

## 11. Pengujian

| Jenis       | Alat                             | Cakupan wajib                                                                               |
| ----------- | -------------------------------- | ------------------------------------------------------------------------------------------- |
| Unit domain | Pest `tests/Unit/Domain`         | Semua kelas `app/Domain`                                                                    |
| Feature     | Pest `tests/Feature/<Modul>`     | Setiap rute: auth, otorisasi, validasi, efek data, audit, notifikasi (`Notification::fake`) |
| Konkurensi  | Pest + MariaDB (`--group=mysql`) | Pembelian ganda, webhook ganda, top-up bersamaan                                            |
| Frontend    | `tsc`, `vp check`                | Semua TSX                                                                                   |
| Browser     | Playwright (Fase 14)             | Wali bayar, scan gerbang, POS, ujian CBT putus-sambung                                      |
| Beban       | k6/skrip PHP (T-1406)            | Gerbang, POS, **CBT 250 siswa serentak** (ADR-020)                                          |
