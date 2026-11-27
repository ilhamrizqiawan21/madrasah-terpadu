# DESIGN — Sistem Desain dan Spesifikasi Layar

Baseline global: `~/Projects/standar-ui-project/admin-panel-standards/` (aturan 01–07). Dokumen ini **tidak menyalin** aturan itu; ia mencatat token proyek, keputusan, dan override.
Override resmi dicatat di §8, dan disalin ke `~/Projects/standar-ui-project/projects/madrasah-terpadu/` lewat tugas T-007 (dengan izin pemilik repo standar).
Saringan anti-slop: skill **antislop** (`antislop-ui`) wajib dijalankan sebelum menyatakan tugas UI selesai (lihat §9).

## 1. Design read

| Pertanyaan                 | Jawaban                                                                                                                                                                                      |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Untuk siapa                | Orang tua (HP Android menengah, sering satu tangan, tidak semua melek teknologi) · staf sekolah (laptop/PC, kerja cepat di tabel) · petugas gerbang & kasir (tablet/PC, satu tugas berulang) |
| Rasa yang dituju           | Tenang, rapi, dapat dipercaya — seperti buku rapor dan kuitansi yang tertata, bukan "startup"                                                                                                |
| Satu hal yang harus terasa | Wali langsung tahu kondisi anaknya hari ini tanpa mencari                                                                                                                                    |
| Yang dihindari             | Kesan "Smart Islamic Campus" yang dilebih-lebihkan, label kapital semua, angka klaim tanpa data, dekorasi tanpa fungsi (ciri aplikasi lama yang akan diganti)                                |
| Dial                       | RHYTHM 2 (aplikasi, ritme stabil, variasi hanya dari hierarki konten) · MOTION 1 (hanya umpan balik interaksi dan transisi panel) · DENSITY: staf 3 (padat), wali 2 (lega)                   |

## 2. Token

Sumber tunggal: CSS variable di `resources/css/app.css` (`@theme` Tailwind 4, format shadcn). Komponen **hanya** memakai token semantik (`bg-background`, `text-muted-foreground`, `border-border`, `bg-primary`, ...). Dilarang warna Tailwind mentah (`bg-green-600`) dan hex di komponen.

### Warna

Arah warna: hijau tua kebiruan (identitas madrasah tanpa jatuh ke hijau neon) di atas netral hangat (kertas). **Nilai final menunggu logo/warna resmi yayasan (PRD §8 no. 8)**; sampai itu, pakai nilai di bawah. Warna utama dapat diubah lewat Pengaturan > Tampilan (T-208), dan turunan hover/active/focus dihitung otomatis.

| Token                            | Light                                             | Dark                                             | Dipakai untuk                                                           |
| -------------------------------- | ------------------------------------------------- | ------------------------------------------------ | ----------------------------------------------------------------------- |
| `--background`                   | `oklch(0.985 0.004 85)`                           | `oklch(0.17 0.01 160)`                           | latar halaman                                                           |
| `--foreground`                   | `oklch(0.22 0.02 160)`                            | `oklch(0.95 0.01 85)`                            | teks utama                                                              |
| `--card`                         | `oklch(1 0 0)`                                    | `oklch(0.21 0.012 160)`                          | permukaan kartu/tabel                                                   |
| `--muted` / `--muted-foreground` | `oklch(0.955 0.006 85)` / `oklch(0.48 0.015 160)` | `oklch(0.26 0.012 160)` / `oklch(0.72 0.01 160)` | latar sekunder / teks sekunder                                          |
| `--border`, `--input`            | `oklch(0.9 0.008 85)`                             | `oklch(0.32 0.012 160)`                          | garis                                                                   |
| `--primary`                      | `oklch(0.42 0.08 165)`                            | `oklch(0.72 0.09 165)`                           | aksi utama, tautan, status aktif menu                                   |
| `--primary-foreground`           | `oklch(0.98 0.005 85)`                            | `oklch(0.18 0.02 165)`                           | teks di atas primary                                                    |
| `--accent-attention`             | `oklch(0.75 0.14 75)`                             | `oklch(0.78 0.13 75)`                            | **hanya** penanda "perlu tindakan" (tagihan jatuh tempo, izin menunggu) |
| `--success`                      | `oklch(0.55 0.12 150)`                            | `oklch(0.72 0.12 150)`                           | lunas, hadir tepat waktu                                                |
| `--warning`                      | `oklch(0.68 0.14 70)`                             | `oklch(0.78 0.13 70)`                            | terlambat, saldo menipis                                                |
| `--destructive`                  | `oklch(0.55 0.19 27)`                             | `oklch(0.68 0.17 27)`                            | hapus, alpa, pelanggaran, gagal                                         |
| `--ring`                         | = `--primary`                                     | = `--primary`                                    | fokus                                                                   |

Palet aktif per layar maksimal: netral + primary + satu warna status yang relevan. `--accent-attention` hanya muncul di titik yang meminta tindakan.

### Tipografi

- **Plus Jakarta Sans** (self-hosted `@fontsource-variable/plus-jakarta-sans`). Alasan: dirancang oleh Tokotype untuk identitas kota Jakarta, nyaman untuk teks Indonesia, angka tegas untuk nominal. Bukan pilihan default "Inter" (lihat antislop R-06).
- Angka uang dan tabel: `font-variant-numeric: tabular-nums` (utility `tabular-nums`), rata kanan.
- Skala: 12 / 14 (dasar staf) / 16 (dasar wali) / 18 / 20 / 24 / 30. Berat: 400 teks, 500 label & tombol, 600 judul. **Tanpa** huruf kapital semua untuk judul atau label (kecuali singkatan resmi: NIS, SPP, BK).

### Jarak, radius, bayangan, gerak

- Spasi skala Tailwind 4 px. Antar-bagian halaman 24/32; dalam kartu 16; antar-field 16; padding sel tabel 12×8 (staf).
- Radius: `--radius: 0.5rem` (8 px) untuk kartu/dialog, 6 px untuk tombol/input, penuh hanya untuk avatar.
- Bayangan: kartu datar dengan border; bayangan hanya untuk elemen melayang (dialog, dropdown, sheet, toast).
- Gerak: 150 ms (`ease-out`) untuk hover/fokus, 200 ms untuk sheet/dialog. Hormati `prefers-reduced-motion`. Tidak ada animasi berulang selain spinner saat menunggu.
- Ikon: Lucide (bawaan shadcn) dengan **stroke 1.75**, ukuran 16 (staf) / 20 (wali). Ikon hanya bila menambah makna atau untuk aksi icon-only. Tanpa ikon dekoratif di setiap judul.

## 3. Layout per area

### Staf (`staff-layout`)

- Topbar: logo + nama unit aktif (kiri), **menu utama rata tengah** berupa grup dropdown (Data Induk, Presensi, Keuangan, Kesiswaan, Ibadah, Ujian, Laporan, Pengaturan), pemilih unit/tahun ajaran, akun (kanan). Sesuai standar 02.
- Di bawah 1024 px: menu pindah ke sheet kiri dengan tombol hamburger (`aria-expanded`, Escape menutup, fokus kembali ke tombol).
- Breadcrumb di setiap halaman di bawah topbar.
- Halaman indeks: `PageHeader` (judul, satu aksi utama di kanan, ekspor icon-only) → filter satu baris → tabel → paginasi.
- Form tambah/ubah: `FormDialog` di tengah (standar 04). Form panjang (penerbitan tagihan, impor) boleh halaman sendiri dengan langkah.
- Beranda staf disusun dari **keputusan** pengguna, bukan template kartu statistik:
    - Bendahara: "Tagihan jatuh tempo minggu ini" + "Pembayaran online belum terekonsiliasi".
    - BK: "Tindak lanjut ambang yang masih terbuka" + "Catatan terbaru".
    - Wali kelas: "Siswa belum tap hari ini" + "Mutabaah menunggu verifikasi" + "Izin menunggu".
    - Admin: kombinasi ringkas di atas + status antrean/backup.

### Wali (`guardian-layout`) — override, lihat §8

- Mobile-first, lebar konten maks 640 px di desktop.
- Header: nama sekolah kecil + pemilih anak (bila > 1 anak) + lonceng kotak masuk.
- Navigasi bawah 4 item: **Beranda**, **Tagihan**, **Riwayat**, **Akun**. Item aktif ditandai bentuk (indikator + tebal), bukan warna saja.
- Beranda per anak: urutan kartu = urgensi. (1) Hari ini: presensi masuk/pulang. (2) Perlu tindakan: tagihan jatuh tempo, mutabaah belum dicentang, penawaran reward. (3) Kantin: saldo + jajan hari ini. (4) Perilaku: catatan terbaru + poin. Kartu yang tidak punya isi disembunyikan, bukan ditampilkan kosong.
- Tombol aksi minimal 44 px tinggi.

### Kiosk (`kiosk-layout`) — gerbang & kantin

- Layar penuh, tanpa menu, hanya nama petugas + tombol keluar kecil.
- Gerbang: input fokus permanen, hasil scan besar (nama, foto, rombel, status berwarna + teks), 10 scan terakhir di sisi kanan. Bunyi pendek berbeda untuk sukses/terlambat/gagal.
- Kantin: kolom kiri identitas siswa + saldo + voucher + sisa limit; kanan papan angka nominal + tombol "Bayar". Konfirmasi dua langkah hanya bila nominal > Rp50.000.

### Ujian (`exam-layout`) — ADR-020

- Layar penuh, tanpa menu. Header: nama siswa, mapel, sisa waktu (teks tenang; warna `--warning` hanya 5 menit terakhir), tombol daftar soal.
- Satu soal per layar; pilihan berupa kartu ≥ 48 px; tanda "ragu-ragu"; navigasi Sebelumnya/Berikutnya di bawah; daftar nomor soal (terjawab, ragu, kosong dibedakan bentuk + teks, bukan warna saja).
- Status sinkron selalu terlihat ("Tersimpan" / "Menyimpan di perangkat, akan dikirim"). Tidak ada hitung mundur besar atau animasi yang menegangkan anak.
- Mode `lab` menampilkan layar "Masuk layar penuh untuk mulai".

### Auth

- Form login terpusat (bukan dua panel, standar 04), logo + nama yayasan di atas form, show/hide password, pesan galat generik.

## 4. Komponen aplikasi (`resources/js/components/app`)

| Komponen                                       | Isi wajib                                                                                                                                                                                                                                                                              |
| ---------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PageHeader`                                   | judul, deskripsi opsional satu baris, slot aksi                                                                                                                                                                                                                                        |
| `SettingsShell`                                | pola pengaturan (mockup 12): judul "Pengaturan", sub-navigasi kiri dari grup menu server `pengaturan` (baris gulir horizontal < 1024 px), satu bagian per halaman dengan judul + deskripsi + garis; slot `actions` (aksi utama di kanan judul bagian) dan `wide` untuk bagian bertabel |
| `DataTable`                                    | header rata tengah; teks kiri, angka kanan, status & aksi tengah; urut kolom; paginasi server; skeleton baris; empty state berkonteks                                                                                                                                                  |
| `RowActions`                                   | icon-only Lihat/Ubah/Hapus dengan tooltip + `aria-label`; Hapus warna destructive + `ConfirmDialog`; aksi khusus modul lewat slot `children`                                                                                                                                           |
| `ExportButtons`                                | icon-only PDF/Excel berwarna identitas format, tooltip                                                                                                                                                                                                                                 |
| `FormDialog`                                   | judul, field, galat inline, tombol Batal/Simpan, loading mencegah submit ganda, Escape, focus trap                                                                                                                                                                                     |
| `ConfirmDialog`                                | menjelaskan dampak, tombol Batal (default fokus) dan Konfirmasi                                                                                                                                                                                                                        |
| `MoneyText`                                    | `Rp 1.250.000`, `tabular-nums`, negatif bertanda minus                                                                                                                                                                                                                                 |
| `StatusBadge`                                  | hanya untuk status nyata; teks + warna token; tanpa titik berdenyut                                                                                                                                                                                                                    |
| `StudentPicker`                                | cari nama/NIS, tampil foto kecil + rombel                                                                                                                                                                                                                                              |
| `EmptyState`                                   | sebab + satu aksi yang mengisi ("Belum ada jenis tagihan. Tambahkan SPP untuk mulai menerbitkan tagihan.")                                                                                                                                                                             |
| `ErrorState`, `NoAccessState`, `OfflineBanner` | sebab + langkah berikutnya                                                                                                                                                                                                                                                             |
| `DateText`                                     | tanggal WITA format `26 Nov 2026`, jam `06.52`                                                                                                                                                                                                                                         |
| `MistakeCounter` (ADR-018)                     | angka besar `tabular-nums`; tombol − / + ≥ 44 px; PageDown/PageUp pada kriteria aktif (ring primary + teks "aktif"); `aria-live`                                                                                                                                                       |
| `ArabicText` (ADR-020)                         | `dir="rtl"`, font Arab self-hosted, kontrol ukuran teks (A− / A+) yang diingat per perangkat                                                                                                                                                                                           |

## 5. Empat keadaan (wajib setiap layar data)

Memuat (skeleton sesuai bentuk data, bukan spinner layar penuh) · Kosong (sebab + aksi) · Galat (apa yang gagal + coba lagi) · Berhasil (toast singkat untuk aksi; data langsung terlihat).
Tambahan bila relevan: tanpa izin, offline, disabled.

## 6. Bahasa UI

- Bahasa Indonesia baku sehari-hari, kalimat pendek, kata kerja di tombol ("Terbitkan tagihan", bukan "Submit").
- Istilah tetap: Wali murid, Siswa, Rombel, Tagihan, Pembayaran, Saldo kantin, Catatan perilaku, Poin kebaikan, Poin pelanggaran, Pembiasaan, Setoran hafalan, Nilai sikap.
- Dilarang di UI produksi: `contoh`, `dummy`, `sample`, lorem, emoji, klaim tanpa data ("100% digital"), petunjuk panjang (standar 07).
- Notifikasi push: subjek jelas + waktu. Contoh pola: "Dais tiba di sekolah · 06.52", "Jajan Rp8.000 · sisa saldo Rp42.000".

## 7. Aksesibilitas

Keyboard penuh, fokus terlihat (`ring`), urutan fokus logis, label untuk setiap input, `aria-label` untuk icon-only, kontras AA di light dan dark, target sentuh ≥ 44 px di area wali dan kiosk, status tidak hanya dengan warna.

## 8. Override terhadap standar-ui-project

| Aturan global                              | Override                                                                        | Alasan                                                                                    |
| ------------------------------------------ | ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| 02: sidebar dengan hamburger / menu topbar | Area **wali** memakai navigasi bawah 4 item di ponsel                           | Wali memakai HP satu tangan; 4 tujuan utama lebih cepat dijangkau ibu jari daripada sheet |
| 02: breadcrumb di setiap halaman           | Area **wali** dan **kiosk** tanpa breadcrumb                                    | Hierarki dangkal (maks 2 level) dan layar tugas tunggal; tombol kembali di header cukup   |
| 04: form CRUD di modal                     | Penerbitan tagihan, impor siswa, dan penilaian setoran memakai halaman bertahap | Form panjang dengan pratinjau tidak muat di modal di layar kecil                          |
| 05: branding banyak client                 | Hanya satu klien: nama, logo, favicon, warna utama saja                         | Bukan produk multi-tenant (PRD §2)                                                        |

## 9. Gerbang anti-slop sebelum tugas UI dinyatakan selesai

Jalankan skill `antislop-ui` dan jawab "ya" untuk semua butir di checklist-nya, ditambah:

- [ ] Layar disusun dari keputusan pengguna (§3), bukan template "4 kartu statistik + grafik + tabel".
- [ ] Tidak ada angka, nama, atau aktivitas karangan di UI; data kosong = empty state jujur.
- [ ] Tidak ada gradien, glassmorphism, glow, titik berdenyut, badge kapsul dekoratif, emoji.
- [ ] Hanya token dari §2; tidak ada warna/hex/radius baru.
- [ ] Diperiksa di 360 px, 768 px, 1280 px, light dan dark, dengan keyboard saja.
- [ ] Empat keadaan (§5) ada dan teksnya menyebut sebab + aksi.
