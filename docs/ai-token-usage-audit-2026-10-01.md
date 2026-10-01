# Audit penggunaan token AI — 1 Oktober 2026

**Status terbaru:** AI-T01 sampai AI-T03 sudah dipatch. Reproduksi di bawah
menjelaskan kondisi sebelum perbaikan; implementasi dan tes regresi tercatat di
[laporan patch](ai-token-usage-patch-2026-10-01.md). Backfill penggunaan chat lama
masih berupa usulan dan belum diimplementasikan.

Audit awal memakai SQLite in-memory, provider HTTP palsu, serta handler dan
observer JavaScript asli dengan DOM minimal dan transport palsu. Tidak ada
panggilan API berbayar atau perubahan data chat lokal. Pemicu terbukti melalui
reproduksi; audit ini tidak memeriksa riwayat insiden pengguna nyata.

## Temuan

Semua temuan P2: perlu diperbaiki dengan pemicu tertentu.

### AI-T01 — Sesi legacy dianggap terukur lengkap setelah satu chat baru

Lokasi: [AiTokenUsageSummary.php:21](../app/Services/Ai/AiTokenUsageSummary.php#L21).

`session()` menentukan kelengkapan hanya dari `ai_chat_runs`. Sesi lama dapat
memiliki pesan assistant tanpa run. `ensureActiveBranch()` memperbaiki rantai
pesan legacy, tetapi tidak membuat run untuk jawaban lama tersebut.

Reproduksi membuat satu pasang pesan lama tanpa run, lalu mengirim chat baru
melalui orchestrator dengan usage 100 input + 10 output. Terdapat dua jawaban
assistant dan satu run terukur. Ringkasan API dan workspace menyatakan
`total=110, status=complete`, walaupun penggunaan jawaban lama masih tidak diketahui.
Angka tersebut seharusnya tetap merupakan pengukuran parsial untuk seluruh sesi.

Arah patch: deteksi jawaban selesai tanpa run sebagai celah pengukuran sesi.
Pertahankan jumlah aktual yang tersedia dan status `partial`; gunakan `unknown`
jika seluruh usage tidak diketahui. Hindari membuat run sintetis atau mengubah
estimasi menjadi pengukuran aktual. Pisahkan sumber estimasi ketika backfill
ditambahkan.

### AI-T02 — Token edit pesan tidak memperbarui penghitung

Lokasi: [workspace.js:442](../resources/js/ai/workspace.js#L442),
[workspace.js:226](../resources/js/ai/workspace.js#L226).

Composer dan pemulihan run memasang callback pembaruan token pada `waitForRun()`.
Alur edit memanggilnya tanpa callback, dan state yang diteruskan ke editor tidak
menyediakan penghitung. Edit sukses akhirnya menavigasi ulang workspace, tetapi
edit gagal atau dibatalkan tetap di halaman yang sama dengan angka lama.

Reproduksi memakai `initMessageInteractions()`, `waitForRun()`, `observeAiRun()`,
dan `initTokenCounter()` dari source. Penghitung awal 110 token; polling edit
mengembalikan status gagal, usage run 220, dan usage sesi 330. Error edit tampil,
tetapi penghitung run dan sesi tetap 110. Reload mengambil angka server terbaru;
bug ini tidak menghilangkan data usage yang telah tersimpan.

Arah patch: teruskan callback status bersama ke seluruh alur composer, resume,
dan edit. Selalu gunakan total sesi server, termasuk pada status terminal gagal
dan batal, tanpa menambahkan total secara lokal.

### AI-T03 — Nama field token reasoning Gemini salah

Lokasi: [LlmTokenUsageParser.php:37](../app/Services/Ai/Providers/LlmTokenUsageParser.php#L37).

Parser membaca `thinkingTokenCount`. Kontrak resmi Gemini menggunakan
`thoughtsTokenCount`; total mencakup prompt, thoughts, dan response candidates.
Sumber: [Gemini API UsageMetadata](https://ai.google.dev/api/generate-content#UsageMetadata).

Payload dengan prompt 100, candidates 10, thoughts 5, dan total 115 menghasilkan
total run 115 yang benar, tetapi `reasoning=null` pada metadata step. Ketika
`totalTokenCount` tidak tersedia, fallback hanya menghasilkan 110 dengan status
parsial, meskipun komponen thoughts 5 tersedia.

Arah patch: baca field resmi dan pertahankan semantics reasoning yang terpisah
dari candidates. Tambahkan fixture provider dengan nama field resmi dan uji
fallback yang memasukkan thoughts tanpa menghitungnya dua kali.

## Verifikasi

- Probe PHP: 3 tes, 13 assertion; semuanya mereproduksi perilaku bermasalah.
- Probe JavaScript: 1 tes; mereproduksi penghitung edit yang tertinggal.
- Suite regresi terkait: 38 tes PHP / 189 assertion dan 19 tes JavaScript lulus.
  Tes yang sudah ada belum mencakup ketiga pemicu di atas.

Probe karakterisasi berada di `storage/framework/testing/`, yang diabaikan Git,
dan sengaja memeriksa perilaku sebelum perbaikan. Lulusnya probe berarti bug
berhasil direproduksi, bukan sudah diperbaiki.

```powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml storage/framework/testing/AiTokenFeatureAuditProbeTest.php
node --test storage/framework/testing/ai-token-feature-audit.mjs
php vendor/bin/pest --configuration phpunit.xml --filter 'AiTokenAccountingTest|AiProviderUsageObservationTest|LlmTokenUsageParserTest|LlmInferenceTest'
node --test tests/Js/ai-token-usage.test.js tests/Js/ai-turn-recovery.test.js tests/Js/ai-pending-turns.test.js
```
