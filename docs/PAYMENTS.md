# PAYMENTS — Integrasi Midtrans

Baca seluruh dokumen ini sebelum menyentuh Fase 7. Uang orang tua dipertaruhkan: **tidak ada improvisasi**.
Referensi resmi: https://docs.midtrans.com (Snap, HTTP Notification, Get Status API). Bila dokumen resmi berbeda dengan dokumen ini, berhenti dan laporkan (AI_RULES §9).

## 1. Prinsip

1. Midtrans hanya untuk **uang masuk**: pelunasan tagihan (`purpose=invoices`) dan isi saldo kantin (`purpose=wallet_topup`).
2. **Satu-satunya** pemicu status lunas/saldo bertambah adalah notifikasi webhook yang (a) lolos `MidtransSignature`, lalu (b) dikonfirmasi Get Status API, dan (c) nominalnya cocok dengan database.
3. Callback JavaScript Snap (`onSuccess`, `onPending`, `onError`, `onClose`) hanya untuk navigasi UI.
4. `gross_amount` selalu dihitung server dari database. Browser hanya mengirim **id tagihan** atau **nominal top-up** yang divalidasi rentangnya.
5. Pemrosesan idempoten: notifikasi yang sama boleh datang berkali-kali dan urutannya tidak dijamin.
6. Server Key hanya di `.env` dan `config('services.midtrans.server_key')`. Client Key boleh ke frontend.

## 2. Konfigurasi

`.env`: `MIDTRANS_IS_PRODUCTION`, `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_MERCHANT_ID` (sudah ada di `.env.example`, dibaca di `config/services.php` ✅).

|                   | Sandbox                                                 | Production                                      |
| ----------------- | ------------------------------------------------------- | ----------------------------------------------- |
| Snap API          | `https://app.sandbox.midtrans.com/snap/v1/transactions` | `https://app.midtrans.com/snap/v1/transactions` |
| Snap JS           | `https://app.sandbox.midtrans.com/snap/snap.js`         | `https://app.midtrans.com/snap/snap.js`         |
| Core API (status) | `https://api.sandbox.midtrans.com/v2/{order_id}/status` | `https://api.midtrans.com/v2/{order_id}/status` |
| Auth              | HTTP Basic, username = Server Key, password kosong      | sama                                            |

URL notifikasi diisi di dashboard Midtrans: `https://<domain>/webhooks/midtrans`. Snap JS dimuat dengan atribut `data-client-key`, hanya di halaman pembayaran, dan domainnya diizinkan di CSP.

Pengecekan siap produksi (T-1404): `MIDTRANS_IS_PRODUCTION=true` **dan** Server Key berawalan `Mid-server-` (bukan `SB-Mid-server-`). Perintah `php artisan payments:check-config` menolak kombinasi yang tidak konsisten. (Aplikasi lama tertangkap memakai sandbox di produksi; ini tidak boleh terulang.)

## 3. Membuat order (Snap)

`PaymentOrderService::createForInvoices(Guardian, Student, invoiceIds)` / `createForTopUp(Guardian, Student, amount)`:

1. Otorisasi: wali terhubung ke siswa. Tagihan milik siswa itu, status `unpaid` (tagihan `partial` dibayar di kasir), belum ada di order `pending` lain yang belum kedaluwarsa.
2. Hitung `gross_amount` = jumlah sisa tagihan. Top-up: `min_top_up ≤ amount ≤ max_top_up` (`config/sekolah.php`).
3. Dalam transaksi: buat `payment_orders` (`order_id` = `INV-` / `TOP-` + ULID, `expires_at` = sekarang + `order_expiry_hours`) + `payment_order_items`.
4. Setelah commit: `MidtransClient::createSnapTransaction()` dengan body:
    ```json
    {
        "transaction_details": {
            "order_id": "INV-01J...",
            "gross_amount": 150000
        },
        "item_details": [
            {
                "id": "invoice-812",
                "price": 150000,
                "quantity": 1,
                "name": "SPP MI November 2026"
            }
        ],
        "customer_details": {
            "first_name": "<nama wali>",
            "phone": "<no HP wali>"
        },
        "enabled_payments": [
            "other_qris",
            "gopay",
            "shopeepay",
            "bca_va",
            "bni_va",
            "bri_va",
            "permata_va",
            "echannel"
        ],
        "expiry": { "unit": "hours", "duration": 24 },
        "callbacks": {
            "finish": "https://<domain>/wali/pembayaran/INV-01J.../selesai"
        }
    }
    ```
    Jumlah `item_details.price × quantity` **harus sama** dengan `gross_amount`. `name` maks 50 karakter.
5. Simpan `snap_token` + `redirect_url`. Gagal memanggil Midtrans → order `failed`, tampilkan galat dan izinkan coba lagi.

## 4. Webhook

`POST /webhooks/midtrans` (`MidtransWebhookController`, tanpa CSRF, `throttle:120,1`):

1. Simpan baris `payment_notifications` (payload mentah, `signature_valid`).
2. Signature tidak valid → respons **403**, log peringatan (tanpa payload lengkap), berhenti.
3. Valid → dispatch `ProcessMidtransNotification(order_id)` ke antrean `payments` → respons **200** `{"status":"ok"}`.

`ProcessMidtransNotification` (`MidtransNotificationProcessor`):

1. `DB::transaction`: ambil order dengan `lockForUpdate()`. Tidak ada order → tandai notifikasi `unknown_order`, selesai.
2. Panggil Get Status API untuk `order_id` (jangan percaya isi webhook saja).
3. Bandingkan `gross_amount` (string Midtrans "150000.00" → integer 150000) dengan order. Beda → `process_result=amount_mismatch`, **jangan** tandai lunas, kirim peringatan ke bendahara.
4. Petakan status lewat `MidtransStatusMapper` (Domain, unit test penuh):

| `transaction_status`                     | `fraud_status` | Status order                                                        |
| ---------------------------------------- | -------------- | ------------------------------------------------------------------- |
| `settlement`                             | —              | `paid`                                                              |
| `capture`                                | `accept`       | `paid`                                                              |
| `capture`                                | `challenge`    | tetap `pending` (bendahara memeriksa di dashboard)                  |
| `pending`                                | —              | `pending`                                                           |
| `expire`                                 | —              | `expired`                                                           |
| `cancel`, `deny`                         | —              | `failed`                                                            |
| `refund`, `partial_refund`, `chargeback` | —              | **tidak diotomatisasi**: tandai `needs_review`, beri tahu bendahara |

5. Transisi ke `paid` hanya dari `pending`/`expired` (pembayaran yang tiba setelah order kedaluwarsa tetap sah). Order sudah `paid` → `process_result=duplicate`, selesai.
6. Saat `paid`:
    - `invoices`: buat `payments` (`method=midtrans`, `payment_order_id`, nomor kuitansi) + `payment_allocations` per item; perbarui `invoices.paid_total` dan status. Tagihan yang sudah lunas lewat kasir di sela waktu itu → kelebihan bayar dicatat sebagai **saldo kantin** siswa dengan entri `adjustment` dan bendahara diberi tahu (keputusan ADR-009).
    - `wallet_topup`: `WalletService::topUp()` dengan `idempotency_key = midtrans:<order_id>`.
    - `AuditLogger`, lalu notifikasi `InvoicePaid` / `WalletToppedUp` ke semua wali (afterCommit).
7. Simpan `last_status_payload`, `payment_type`, `midtrans_transaction_id`, `paid_at`; tandai `payment_notifications.processed_at`.

## 5. Kedaluwarsa dan rekonsiliasi

- `ExpirePaymentOrders` (setiap 15 menit): order `pending` dengan `expires_at` < sekarang − 15 menit → cek Get Status → petakan (bisa saja ternyata sudah lunas).
- `payments:reconcile {date?}` (harian 01.00 WITA): semua order yang dibuat kemarin dibandingkan dengan Get Status. Selisih ditulis ke log dan dikirim sebagai notifikasi ke bendahara. Tidak ada koreksi otomatis selain yang diizinkan oleh tabel status di §4.

## 6. Skenario uji wajib (Http::fake, tanpa jaringan)

1. Webhook `settlement` valid → tagihan lunas, kuitansi dibuat, wali dapat notifikasi.
2. Webhook yang sama dikirim 2x berturut-turut → hanya satu pembayaran.
3. Dua webhook diproses bersamaan (tes MariaDB) → hanya satu pembayaran.
4. Signature salah → 403, tidak ada perubahan.
5. Signature benar tetapi Get Status mengembalikan `pending` → tidak lunas.
6. Nominal berbeda → `amount_mismatch`, tidak lunas, bendahara diberi tahu.
7. `expire` → order `expired`, tagihan dapat dibayar lagi.
8. `settlement` datang setelah `expire` → lunas.
9. Order tidak dikenal → dicatat, 200, tidak ada perubahan.
10. Tagihan sudah dilunasi kasir sebelum webhook → kelebihan masuk saldo kantin + notifikasi bendahara.
11. Top-up `settlement` → saldo bertambah sekali.
12. Browser mengirim `gross_amount` palsu ke endpoint pembuatan order → diabaikan (nominal dari DB).
13. Wali mencoba membayar tagihan anak lain → 404.
14. `capture` + `challenge` → tetap pending.

## 7. Uji manual di sandbox (T-708)

Pakai simulator pembayaran sandbox Midtrans untuk QRIS/GoPay/VA. Untuk webhook di lokal, gunakan tunnel (mis. `cloudflared tunnel --url http://localhost:8000`) dan isi URL notifikasi sandbox ke URL tunnel. Catat hasil setiap skenario §6 di bukti tugas.
