# Follow-up Audit Percabangan Chat dan Transparansi Proses AI 002

Tanggal: 15 September 2026

Status: temuan 1-10 disetujui dan diimplementasikan.

## Perubahan yang diterapkan

1. Pesan completed menjadi immutable. Edit membuat branch dan revisi baru tanpa mengubah atau menghapus branch lama.
2. Sesi memiliki active branch; request tumpang tindih ditolak dengan HTTP 409 melalui durable run state dan row lock sesi.
3. Limit konteks 10 pesan dihapus. `ConversationContextAssembler` memakai ancestry branch aktif, anggaran karakter konservatif, dan digest terenkripsi untuk bagian lama.
4. Workspace menampilkan tail 100 pesan dan menyediakan cursor “Muat percakapan sebelumnya”.
5. Setiap generasi memiliki run dan step append-only untuk reasoning summary serta tool call, termasuk status dan durasi.
6. `reasoning_content` mentah tetap terenkripsi dan tidak diserialisasi ke browser. UI hanya menerima label proses yang aman dan metadata allowlist.
7. Edit, switch branch, dan pagination selalu diawali dari resource milik authenticated user; resource asing menghasilkan 404.
8. Bubble pengguna memiliki edit inline, Save/Cancel, Escape, draft-preserving error, action hover/focus, touch target, dan navigator versi.
9. Jawaban assistant memiliki disclosure `Proses AI · n dtk`; reasoning dan aktivitas alat memakai hierarki serta status tekstual yang berbeda.
10. Response chat sekarang membawa stable message ID, active branch, revision metadata, public run steps, dan assistant payload.

## Verifikasi

- PASS: migration `2026_09_15_000005_add_branching_and_run_steps_to_ai_chat` diterapkan pada MySQL lokal dan backfill selesai.
- PASS: 221 automated tests / 1087 assertions pada full suite.
- PASS: 8 test baru / 39 assertions untuk branching, version switch, cross-user denial, overlapping generation, deep history, reasoning privacy, rendering control, dan pagination.
- PASS: produksi frontend berhasil dibangun dengan Vite.
- PASS: seluruh Blade template berhasil dikompilasi melalui `php artisan view:cache`.
- PASS: PHP Pint dijalankan pada seluruh file PHP yang disentuh.
- PASS: light/dark text tokens memenuhi rasio normal-text; border token light memenuhi non-text contrast 3:1.
- UNVERIFIED: clickthrough dan screenshot pada browser nyata karena tidak ada browser yang tersedia di environment eksekusi ini.

## Catatan batas

- Seluruh transcript dipertahankan di database, tetapi input mentah ke provider tetap tunduk pada context window. Bagian lama diringkas menjadi digest agar konteks praktis tetap tersedia.
- Pergantian versi melakukan reload terarah setelah server mengaktifkan branch. Ini sengaja dipilih agar DOM, proposal state, pagination cursor, dan ancestry selalu berasal dari snapshot server yang konsisten.
