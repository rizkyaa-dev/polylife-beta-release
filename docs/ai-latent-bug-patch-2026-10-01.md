# Patch bug laten AI — 1 Oktober 2026

Kelima temuan dalam [audit](ai-latent-bug-audit-2026-10-01.md) telah diperbaiki.
Perubahan memakai resolver, assembler, adapter provider, dan boundary inference
yang sudah ada, dengan parser usage bersama dan satu registry pending turn di
browser. Tidak ada migrasi database tambahan atau perubahan konfigurasi provider.

| Temuan | Perilaku setelah patch | Regresi |
|---|---|---|
| AI-L01: nama persis sama memilih record pertama | Exact dan partial match dibatasi dua kandidat dan sama-sama menolak ambiguitas. Ownership, scope, dan prioritas unique exact match tetap berlaku. | Duplikat exact termasuk perbedaan kapitalisasi, pemisahan tenant, unique exact vs partial, scope query yang bisa digunakan ulang |
| AI-L02: arsip tool melewati budget | Seluruh output assembler memakai satu budget, termasuk facts, reasoning dan header digest. Facts utuh mendapat maksimal 20% budget, capped 12.000 karakter; facts terbaru diprioritaskan. Cached digest dibatasi ulang saat budget turun. | Budget 2.000 dan default 24.000 token, facts besar/kecil, JSON tetap valid, reasoning Unicode, cached digest |
| AI-L03: pending edit tertinggal setelah Stop | Composer, edit dan reload recovery memakai registry yang sama. Pembatalan yang terkonfirmasi membersihkan turn sesuai runId; run yang sudah completed tetap bisa direkonsiliasi. Observer terputus tidak membuka pengiriman lain. | Stop setelah polling putus, completed memenangkan race, terminal failure, request identity saat acceptance hilang, draft saat editor dibuka kembali |
| AI-L04: usage rusak menjadi measured zero | Parser menerima counter integer nonnegatif dalam rentang penyimpanan. Metadata tanpa counter dikenali menjadi unknown. Counter parsial tetap tersimpan sebagai lower bound; komponen yang hilang tidak dikarang. | Nilai negatif, string/bool/array/float, overflow, total inkonsisten, zero sah, partial usage |
| AI-L05: usage hilang pada refusal/response invalid | Adapter mengamati usage sebelum validasi content/refusal. Wrapper mempertahankan observation tepat sekali per attempt. Respons yang ditolak tetap membuat run gagal. | OpenAI refusal/content filter/empty content/empty choices; Gemini safety/prompt block/empty candidates; nested observer dan retry |

Resolusi reminder mempersempit kandidat ke target yang benar-benar memiliki
reminder milik pengguna, termasuk `current_time` jika diberikan. Dua item bernama
sama dengan hanya satu reminder tetap menghasilkan target yang unik. Jika kedua
item memiliki reminder yang sesuai, tool meminta klarifikasi; waktu eksplisit
dapat membedakannya. Pembuatan reminder baru tetap menolak target ambigu.

Pengukuran lengkap dibedakan dari informasi parsial pada DTO usage. Counter run
dan step hanya menganggap measurement lengkap sebagai `measured_model_calls`;
counter token yang tersedia pada laporan parsial tetap terakumulasi. Session
summary menampilkan `partial` ketika jumlah positif tersedia tetapi pengukuran
seluruh call belum lengkap.

## Pemeriksaan

Suite permanen menggantikan probe karakterisasi sebelum patch:

- `tests/Feature/Ai/OwnedWorkspaceRecordResolverTest.php`
- `tests/Feature/Ai/ReminderRecordResolverTest.php`
- `tests/Feature/Ai/ConversationContextAssemblerTest.php`
- `tests/Feature/Ai/AiProviderUsageObservationTest.php`
- `tests/Unit/Ai/LlmTokenUsageParserTest.php`
- `tests/Js/ai-pending-turns.test.js`

Hasil final:

- `php artisan test --compact`: **605 passed, 5.152 assertions**.
- `node --test tests/Js/*.test.js`: **98 passed**.
- Pint untuk seluruh PHP yang disentuh patch ini: passed.
- `git diff --check`: passed.
- `npm run build`: passed; aset lokal sudah dibangun ulang.
- `php artisan queue:restart`: sinyal restart worker lokal berhasil dikirim.

Tes provider memakai HTTP fake dan SQLite terisolasi; tes browser menjalankan
handler asli dengan adapter DOM/transport deterministik. Tidak ada request ke
provider berbayar, benchmark contention produksi, atau pengujian browser
end-to-end pada patch ini. Budget konteks tetap estimasi riwayat, bukan tokenizer
atau batas seluruh request provider.

Untuk lingkungan yang sudah menjalankan migrasi token dari hardening sebelumnya,
deployment ini cukup membangun aset dan memuat ulang worker PHP agar memakai
adapter terbaru. Pengukuran usage yang sudah hilang pada run lama tidak dapat
direkonstruksi dari database.

```shell
npm run build
php artisan queue:restart
```
