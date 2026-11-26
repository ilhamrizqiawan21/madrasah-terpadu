# DECISIONS — Catatan Keputusan Arsitektur (ADR)

Status: **Diterima** (mengikat, jangan dibalik tanpa ADR baru dari pemilik) · **Usulan** (menunggu keputusan) · **Ditolak**.
Agen yang menemukan kebutuhan keputusan baru menulis di bagian _Usulan_ lalu berhenti (AI_RULES §9).

---

## ADR-001 — Laravel 13 + Inertia 3 + React 19 + shadcn/ui (Diterima, 2026-11-26)

**Konteks:** pemilik memakai shadcn/ui dan menginginkan hasil yang rapi dan tidak terlihat "AI slop". Repo PIB memakai Livewire; repo penilaian-tahfidz memakai Inertia.
**Keputusan:** starter kit resmi `laravel/react-starter-kit` (cabang `main`): Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Fortify, Wayfinder.
**Akibat:** komponen shadcn dapat dipakai langsung; logika domain dari PIB (PHP) tetap dapat di-port; UI Livewire PIB tidak dipakai ulang. Satu bahasa frontend (TSX).
**Alternatif ditolak:** Livewire 4 + Blade (shadcn tidak tersedia resmi; menyalin gaya secara manual memperbesar risiko inkonsistensi).

## ADR-002 — Monolit dengan Service + Domain (Diterima)

Satu aplikasi Laravel. Penulisan data hanya lewat `app/Services`; perhitungan murni di `app/Domain`. Tanpa microservice, tanpa API terpisah untuk UI.

## ADR-003 — MySQL/MariaDB di produksi, SQLite untuk tes cepat (Diterima)

Tes default di SQLite in-memory. Tes yang menyangkut lock, konkurensi, dan ledger diberi `->group('mysql')` dan dijalankan terhadap MariaDB lokal di `composer check:mysql` (T-005). Dilarang SQL khusus dialek.

## ADR-004 — Multi-unit dalam satu yayasan, bukan multi-tenant (Diterima)

Tabel `units` (MI, RA, TPQ). Data milik unit difilter oleh scope dan policy. Tidak ada database per sekolah, tidak ada branding per unit.

## ADR-005 — Notifikasi wali lewat Web Push + kotak masuk; WhatsApp disiapkan, tidak aktif (Diterima)

Web Push gratis dan memberikan notifikasi melayang dengan suara. Setiap notifikasi juga disimpan di channel `database` agar tidak hilang. Channel WhatsApp dibuat sebagai antarmuka (`WhatsAppChannel` no-op) agar dapat diaktifkan nanti tanpa mengubah kelas notifikasi.

## ADR-006 — Uang dan poin memakai ledger append-only (Diterima)

`wallet_entries` dan `point_entries` tidak pernah di-update atau dihapus. Koreksi = entri pembalik. Kolom saldo hanyalah cache yang diperbarui di transaksi yang sama (dengan `lockForUpdate`) dan diverifikasi harian. Alasan: aplikasi lama menunjukkan saldo yang tidak konsisten antar-halaman; ledger membuat setiap rupiah dan poin dapat ditelusuri.

## ADR-007 — Midtrans via Laravel HTTP Client, tanpa SDK resmi (Diterima)

SDK `midtrans/midtrans-php` memakai konfigurasi statis global yang sulit dites. Endpoint yang dipakai hanya dua (Snap create, Get Status). `MidtransClient` tipis + `Http::fake()` di tes. Verifikasi signature di `App\Domain\Payments\MidtransSignature` (sudah ada, 11 tes).

## ADR-008 — Kartu QR dengan token acak + scanner barcode sebagai input utama (Diterima)

Token 32 byte acak (bukan NIS) yang disimpan sebagai hash, dapat dicabut. Scanner barcode USB/Bluetooth (bekerja sebagai keyboard) adalah jalur utama karena murah dan cepat; kamera adalah alternatif. Biometrik di luar cakupan.

## ADR-009 — Kelebihan bayar online masuk saldo kantin (Usulan — konfirmasi klien)

Bila tagihan sudah lunas lewat kasir sebelum webhook Midtrans tiba, kelebihan bayar dicatat sebagai saldo kantin siswa dan bendahara diberi tahu. Alternatif: dikembalikan manual oleh bendahara. Default sampai diputuskan: saldo kantin.

## ADR-010 — Pembayaran online hanya pelunasan penuh per tagihan (Diterima)

Wali boleh memilih beberapa tagihan, tetapi tiap tagihan dibayar penuh. Cicilan hanya lewat kasir. Alasan: alokasi parsial online menambah kasus tepi tanpa manfaat besar bagi klien.

## ADR-011 — Notifikasi dikirim setelah commit lewat antrean (Diterima)

`ShouldQueueAfterCommit` untuk semua notifikasi domain. Worker antrean wajib berjalan di produksi (Supervisor, lihat DEPLOYMENT).

## ADR-012 — Zona waktu WITA (Diterima)

Kolaka, Sulawesi Tenggara = `Asia/Makassar`. Semua "hari sekolah" (`school_date`, `log_date`) dihitung dalam WITA. Sudah dikonfigurasi di fondasi.

## ADR-013 — Desain mengikuti standar-ui-project + antislop, font Plus Jakarta Sans (Diterima)

Lihat DESIGN.md. Override terhadap standar global dicatat di DESIGN.md §8 (disalin ke `standar-ui-project/projects/madrasah-terpadu/` di T-007).

## ADR-014 — Registrasi publik dimatikan; akun dibuat oleh sekolah (Diterima)

Akun wali dibuat lewat impor/data induk dengan password awal acak. Tidak ada halaman daftar publik.

## ADR-015 — Wajib ganti password saat login pertama dinonaktifkan sementara (Diterima, 2026-11-26)

**Keputusan pemilik:** fitur paksa ganti password (`must_change_password`) **tidak dibangun** untuk saat ini. Pengguna tetap dapat mengganti password sendiri di halaman Akun, dan admin dapat me-reset password.
**Akibat:** kolom `must_change_password` tidak dibuat; tidak ada middleware atau redirect paksa. Bila diaktifkan kembali nanti: migration baru + ADR baru.
**Agen:** jangan menambahkan kolom, middleware, atau alur paksa ganti password.

---

## Usulan terbuka (menunggu klien, lihat PRD §8)

- **U-01** Rumus nilai sikap (PRD §4.11). Memblokir Fase 12.
- **U-02** Biaya admin Midtrans ditanggung siapa (PRD §4.5). Default: yayasan.
- **U-03** Keterlambatan otomatis menjadi pelanggaran (PRD §4.7). Default: tidak.
- **U-04** Streak mutabaah menghasilkan poin (PRD §4.9). Default: tidak.
- **U-05** ADR-009 kelebihan bayar.
- **U-06** Nama pencatat perilaku terlihat oleh wali. Default: tidak terlihat.
