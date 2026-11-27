# Panduan Hari-H Ujian CBT — Madrasah Terpadu

Panduan operasional untuk admin, guru, wali kelas, dan pengawas ruangan saat pelaksanaan ujian berbasis komputer (CBT).

---

## 1. Persiapan H-3 s.d. H-1

1. **Kunci fitur & jadwal ujian**
    - Pastikan fitur **Ujian CBT** aktif di **Pengaturan > Kunci fitur**.
    - Pastikan ujian berstatus **Terjadwal** (`scheduled`) atau **Berlangsung** (`running`), rombel peserta sudah dicentang, jendela waktu (`opens_at`–`closes_at`) dan durasi (`duration_minutes`) sesuai.
    - Pastikan seluruh soal sudah masuk sebelum ujian dimulai; **soal otomatis terkunci setelah siswa pertama memulai sesi** (koreksi kunci setelah ujian dimulai hanya bisa dilakukan admin dengan alasan wajib dan tercatat di jejak audit).

2. **PIN ujian siswa (`/admin/ujian/pin-siswa`)**
    - Wali kelas atau admin menekan **Buat ulang PIN rombel** untuk menghasilkan PIN 6 digit baru bagi seluruh siswa aktif di rombel.
    - **Cetak segera** daftar PIN dari dialog sekali tampil tersebut dan bagikan ke pengawas ruangan / siswa pada hari ujian. PIN tidak bisa dilihat kembali setelah dialog ditutup.

3. **Token ujian (opsional)**
    - Bila ujian memakai **Token ujian**, catat token tersebut dan tuliskan di papan tulis hanya sesaat sebelum pengawas memberi aba-aba mulai.

4. **Uji beban & kesiapan server (sebelum hari-H)**
    - Pastikan worker antrean (`queue:work`) dan Laravel Scheduler (`schedule:run` tiap menit) aktif di server produksi agar penilaian otomatis (`ScoreExamSession`) dan penutupan sesi lewat waktu (`CloseExpiredExamSessions`) berjalan.
    - Untuk target 250 siswa serentak, lakukan uji beban (T-1406) pada server target sebelum hari-H.

---

## 2. Persiapan Per Mode Perangkat

Pengaturan mode perangkat dipilih per ujian di **Ujian > Daftar ujian**:

| Mode     | Perangkat           | Perilaku Sistem                                                                                                                                               | Persiapan Pengawas                                                                                                |
| -------- | ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `lab`    | Komputer/laptop lab | Layar penuh wajib; keluar layar penuh, pindah tab, atau salin-tempel dihitung sebagai pelanggaran; sesi otomatis **terkunci** saat mencapai `violation_limit` | Aktifkan mode layar penuh browser atau Safe Exam Browser (SEB) opsional; ingatkan siswa tidak menekan Esc/Alt+Tab |
| `tablet` | Tablet sekolah      | Layar penuh bila didukung browser; pindah aplikasi/tab dan salin-tempel dihitung dan mengunci sesi saat mencapai batas                                        | Aktifkan **Guided Access** (iPadOS) atau **App Pinning / Mode Kios** (Android) sebelum dibagikan ke siswa         |
| `phone`  | HP orang tua        | Pelanggaran dicatat di log (`counted=false`) tetapi **tidak mengunci sesi** agar panggilan/notifikasi masuk tidak memutus ujian                               | Gunakan Exambro opsional bila tersedia; pastikan mode `Jangan Ganggu` aktif dan kuota/Wi-Fi stabil                |

---

## 3. Simulasi Putus Wi-Fi (Verifikasi Wajib Sebelum Ujian)

Sebelum hari-H, lakukan simulasi singkat dengan 1 perangkat uji:

1. Masuk di `/ujian/masuk` memakai NIS + PIN siswa uji dan mulai ujian.
2. Jawab 2–3 soal (pastikan indikator header menunjukkan **Tersimpan**).
3. Matikan Wi-Fi perangkat selama **60 detik**, lalu jawab 2 soal berikutnya — indikator berubah menjadi **Menyimpan di perangkat** (jawaban tersimpan di `localStorage`).
4. Nyalakan kembali Wi-Fi dan jawab/ubah 1 soal atau muat ulang halaman: pastikan seluruh jawaban tersinkronisasi dan urutan soal serta sisa waktu server tetap utuh.

---

## 4. Saat Hari-H (Monitor Pengawas)

Buka **Ujian > Daftar ujian → Monitor pengawas** (pembaruan otomatis tiap 5 detik):

- **Belum mulai**: siswa belum menekan _Mulai ujian_.
- **Mengerjakan**: siswa sedang aktif; pantau kolom progres (`terjawab / total soal`) dan pelanggaran.
- **Terkunci**: sesi terkunci karena melebihi batas pelanggaran (`lab`/`tablet`). Pengawas dapat memeriksa kondisi siswa lalu menekan tombol **Buka kunci** agar siswa dapat melanjutkan.
- **Tambah waktu**: gunakan bila terjadi gangguan listrik/jaringan di ruangan tertentu (1–120 menit).
- **Paksa selesai**: hentikan sesi siswa yang sudah meninggalkan ruangan tanpa menekan _Selesai ujian_; jawaban yang masuk akan langsung dinilai melalui antrean.
- **Pindah perangkat (take-over)**: jika perangkat siswa mati, siswa cukup masuk kembali dari perangkat pengganti dengan NIS + PIN yang sama. Perangkat lama otomatis ditolak (`409`) dan urutan soal serta sisa waktu tetap sama.

---

## 5. Pasca-Ujian

1. Untuk ujian yang memiliki **Essay kertas**, buka **Nilai essay**, masukkan nilai (0 s.d. bobot maksimal essay) secara manual atau impor Excel (`.xlsx` kolom `nis, question_order, score`). Status hasil otomatis berubah dari _Menunggu nilai essay_ menjadi _Final_.
2. Buka **Hasil ujian** untuk melihat rekap per rombel dan mengunduh ekspor Excel (`.xlsx`).
3. Hasil ujian hanya terlihat di portal wali (`/wali/anak/{student}/ujian`) apabila opsi **Tampilkan hasil ke wali** diaktifkan dan status hasil sudah **Final**.
