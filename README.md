# Madrasah Terpadu

Aplikasi sekolah untuk MI, RA, dan TPQ Darul Istiqamah (Yayasan Amal Jaya Berkah, Kolaka). Menghubungkan sekolah dengan orang tua:
presensi gerbang, notifikasi ke HP wali, tagihan dan pembayaran online (Midtrans), dompet kantin, Buku Saku BK dengan poin dan reward,
pembiasaan harian, setoran hafalan, dan nilai sikap.

**Status:** Fase 0 (fondasi) selesai, 2026-11-26. Rencana kerja ada di [docs/TODO.md](docs/TODO.md).

## Stack

PHP 8.4+ · Laravel 13 · Inertia 3 · React 19 + TypeScript · Tailwind CSS 4 · shadcn/ui · Fortify · Wayfinder · spatie/laravel-permission · MySQL/MariaDB · Pest 4 · Larastan · Pint · Vite+.

## Mulai cepat

```bash
composer setup     # install dependensi, .env, key, migrate, build aset
composer dev       # server lokal + antrean + vite
composer check     # gerbang mutu: format, analisis statis, tes, typecheck, lint
```

Lingkungan lokal memakai SQLite secara default. Untuk MariaDB, ubah `DB_*` di `.env`.

## Dokumen

| Dokumen                                                       | Isi                                                                         |
| ------------------------------------------------------------- | --------------------------------------------------------------------------- |
| [docs/PRD.md](docs/PRD.md)                                    | Produk, aturan bisnis, peran, pertanyaan terbuka untuk klien                |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)                  | Struktur kode, alur kritis (notifikasi, pembayaran, ledger, scan), keamanan |
| [docs/SCHEMA.md](docs/SCHEMA.md)                              | Rancangan database per modul                                                |
| [docs/DESIGN.md](docs/DESIGN.md)                              | Sistem desain, layout per area, gerbang anti-slop                           |
| [docs/PAYMENTS.md](docs/PAYMENTS.md)                          | Integrasi Midtrans dan skenario uji                                         |
| [docs/DECISIONS.md](docs/DECISIONS.md)                        | Keputusan arsitektur dan usulan terbuka                                     |
| [docs/REUSE_MAP.md](docs/REUSE_MAP.md)                        | Kode rujukan dari SIPESAN, PIB, penilaian-tahfidz                           |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)                      | Panduan server, worker, cron, backup                                        |
| [docs/TODO.md](docs/TODO.md)                                  | Rencana eksekusi per fase                                                   |
| [GEMINI.md](GEMINI.md) · [docs/AI_RULES.md](docs/AI_RULES.md) | Panduan dan aturan untuk agen AI eksekutor                                  |
