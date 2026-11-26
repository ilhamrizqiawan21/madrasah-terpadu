# AGENTS.md

Instruksi untuk semua agen AI di repo ini (Gemini, Claude, Codex, Cursor, dan lainnya).

1. Baca `GEMINI.md` (ringkasan operasional) lalu `docs/AI_RULES.md` (aturan mengikat) sebelum menyentuh kode. Aturan itu berlaku untuk semua agen, bukan hanya Gemini.
2. Kerjakan **satu paket** dari `docs/TODO.md` (bagian "Rencana rilis & paket") per putaran, di jalur yang ditugaskan. Di dalam paket: satu tugas selesai (tes + `composer check` + commit) sebelum tugas berikutnya. Aturan rinci: `docs/AI_RULES.md` §2.
3. Untuk pekerjaan UI: baca `docs/DESIGN.md` dan `~/Projects/standar-ui-project/admin-panel-standards/`, lalu jalankan skill `antislop-ui` sebelum menyatakan selesai.
4. Untuk pembayaran dan dompet: baca `docs/PAYMENTS.md` seluruhnya.
5. `composer check` harus hijau sebelum tugas dinyatakan selesai.
6. Peran arsitek dipegang Claude: keputusan desain baru ditulis sebagai _Usulan_ di `docs/DECISIONS.md`, bukan langsung diimplementasikan.
