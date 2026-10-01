# Audit bug laten AI — 1 Oktober 2026

**Status terbaru:** AI-L01 sampai AI-L05 telah dipatch. Bagian reproduksi di bawah
menjelaskan kondisi sebelum patch; implementasi dan tes regresi terbaru dicatat
di [laporan patch](ai-latent-bug-patch-2026-10-01.md). Probe sementara lama adalah
karakterisasi sebelum patch dan bukan suite regresi yang harus tetap lulus.

Audit ini menilai working tree saat ini, termasuk hardening sebelumnya. Lima
temuan di bawah terbukti melalui reproduksi terisolasi. Audit tidak memeriksa
riwayat insiden produksi, sehingga tidak menyatakan bahwa pemicunya belum pernah
terjadi pada pengguna nyata. Tidak ada perubahan kode aplikasi atau konfigurasi
deployment dalam audit ini.

## Temuan dan urutan perbaikan

Semua temuan berprioritas P2: perlu diperbaiki, dengan pemicu tertentu. Urutan
pengerjaan disarankan berdasarkan dampak terhadap data dan pemulihan percakapan.

| Urutan | ID | Masalah | Pemicu laten | Dampak |
|---|---|---|---|---|
| 1 | AI-L01 | Nama persis sama melewati pemeriksaan ambiguitas | Dua record milik pengguna memiliki nama sama | Proposal memilih satu record tanpa klarifikasi; konfirmasi dapat mengubah record yang berbeda dari maksud pengguna |
| 2 | AI-L03 | State edit tertinggal setelah pembatalan berhasil | Edit diterima, polling terputus, pengguna menekan Stop, lalu mengganti draft edit | Edit baru ditolak meskipun run sudah dibatalkan |
| 3 | AI-L02 | Arsip tool ditambahkan di luar budget konteks | Riwayat mendekati batas konteks dan memiliki hasil tool tersimpan | Input lebih besar dari kebijakan budget; meningkatkan konsumsi konteks dan dapat memicu penolakan provider jika melewati kapasitas model |
| 4 | AI-L05 | Usage valid hilang ketika respons ditolak adapter | Refusal, safety block, atau respons tidak usable yang masih membawa usage | Run gagal kehilangan jumlah token yang sebenarnya tersedia dari provider |
| 5 | AI-L04 | Usage tidak valid dianggap pengukuran lengkap | Objek usage berisi field tak dikenal atau tidak memiliki counter yang dikenali | Angka nol ditampilkan sebagai pengukuran lengkap, bukan unknown |

## AI-L01 — Resolusi nama persis sama memilih satu record

Lokasi: [OwnedWorkspaceRecordResolver.php:39](../app/Services/Ai/OwnedWorkspaceRecordResolver.php#L39),
[ManageTodolistTool.php:47](../app/Services/Ai/Tools/ManageTodolistTool.php#L47).

`resolveFromOwnedQuery()` menggunakan `first()` untuk exact match dan langsung
mengembalikannya. Pemeriksaan `count() > 1` hanya berlaku pada pencarian parsial.
Duplikat nama diizinkan oleh model to-do; nama bukan identitas unik.

Reproduksi membuat dua to-do `Beli buku`, kemudian memanggil `manage_todolist`
dengan operasi `complete`. Tool menghasilkan proposal untuk salah satunya tanpa
error ambiguitas. Menjalankan payload melalui action konfirmasi menyelesaikan
record pertama pada fixture SQLite dan membiarkan record kedua terbuka. Kontrol
dengan dua nama yang hanya cocok parsial menghasilkan error ambiguitas.

Ownership dan snapshot freshness tetap berjalan, tetapi keduanya tidak membuktikan
bahwa record yang dipilih sesuai maksud pengguna. Summary proposal juga hanya
menyebut nama yang sama, sehingga pengguna tidak memperoleh pembeda yang cukup.
Resolver dipakai oleh berbagai tool untuk tugas, jadwal, catatan, reminder,
keuangan, dan mata kuliah; reproduksi langsung dilakukan pada to-do.

Arah patch: pertahankan prioritas exact match, tetapi ambil maksimal dua kandidat
dan tolak jika ambigu. Bila pengguna harus bisa memilih record bernama sama,
tambahkan identitas record atau pembeda domain yang eksplisit pada kontrak tool;
tetap validasi ownership sebelum membuat proposal. Jangan memaksakan unique nama
global pada data yang secara domain boleh memiliki nama sama.

## AI-L03 — Pembatalan tidak menyelesaikan pending edit

Lokasi: [workspace.js:93](../resources/js/ai/workspace.js#L93),
[workspace.js:371](../resources/js/ai/workspace.js#L371),
[workspace.js:410](../resources/js/ai/workspace.js#L410).

Composer menyimpan pengiriman tertunda dalam `retryRequest`, sedangkan edit pesan
menyimpannya dalam `pendingEdits`, sebuah `WeakMap` lokal. Setelah polling gagal,
edit mempertahankan accepted run dan mengembalikan `busy` menjadi false. Handler
Stop mengirim pembatalan, memancarkan `ai:run-cancelled`, dan membersihkan state
composer. State edit tidak mengikuti acknowledgement pembatalan tersebut.

Probe menjalankan fungsi `initMessageInteractions()` asli dengan adapter DOM dan
transport terkontrol: PATCH diterima sebagai run 7, polling melempar kegagalan
jaringan, pembatalan berhasil disimulasikan sesuai kontrak handler Stop, lalu
draft diubah. Pengiriman edit kedua tidak terjadi; UI menampilkan
`Edit sebelumnya belum diketahui hasilnya...`.

Workaround yang terbukti: retry draft lama hingga polling mengembalikan status
cancelled, atau muat ulang halaman. Ini bukan pengujian browser end-to-end.
Sebagai konsekuensi terkait, `isBusy()` hanya membaca flag polling, bukan adanya
run yang diterima dan belum diselesaikan; interaksi lain bisa kembali aktif saat
status run belum pasti. Dampak interaksi tambahan ini belum diuji end-to-end.

Arah patch: satukan lifecycle accepted/pending turn untuk composer dan edit,
atau gunakan registry terpusat yang bisa diselesaikan berdasarkan `runId`.
Acknowledgement cancellation harus membersihkan turn yang cocok dan mempertahankan
draft. Bedakan aktivitas observer dari keberadaan run yang belum terminal.
Pertahankan request identity pada kegagalan jaringan yang belum diketahui hasilnya.

## AI-L02 — Budget konteks tidak mencakup arsip tool

Lokasi: [ConversationContextAssembler.php:29](../app/Services/Ai/ConversationContextAssembler.php#L29),
[ConversationContextAssembler.php:175](../app/Services/Ai/ConversationContextAssembler.php#L175),
[ConversationContextAssembler.php:186](../app/Services/Ai/ConversationContextAssembler.php#L186).

Assembler menghitung budget hanya dari content dan reasoning pesan. Sesudah
memilih atau merangkum pesan, `withToolFacts()` menambahkan satu system message
hingga 12.000 karakter JSON, beserta teks pembukanya. Tidak ada reservasi ruang
atau pemeriksaan ukuran akhir.

Reproduksi menggunakan satu pesan assistant completed dan hasil tool completed
dari run yang terkait dengannya:

| Konfigurasi budget | Budget internal, 3 karakter/token | Ukuran hasil assembler |
|---|---:|---:|
| 2.000 token | 6.000 karakter | 16.089 karakter |
| Default 24.000 token | 72.000 karakter | 80.289 karakter |

Angka di atas membuktikan pelanggaran kebijakan karakter internal, bukan ukuran
token hasil tokenizer provider. Penolakan context-window atau peningkatan biaya
pada provider nyata belum diuji. System instruction, schema tool, dan prompt turn
baru di luar assembler juga tidak dihitung dalam angka reproduksi ini.

Arah patch: alokasikan satu budget untuk seluruh keluaran assembler, termasuk
arsip tool dan teks pembuka. Batasi facts dari sisa budget, sisakan ruang bagi
pesan terbaru, lalu verifikasi ukuran akhir. Untuk pembatasan seluruh request,
reservasi schema, instruksi, input baru, dan output perlu dilakukan pada lapisan
yang mengetahui request lengkap.

## AI-L05 — Usage tersedia tetapi hilang pada respons yang ditolak

Lokasi: [OpenAiLlmClient.php:99](../app/Services/Ai/Providers/OpenAiLlmClient.php#L99),
[OpenAiLlmClient.php:156](../app/Services/Ai/Providers/OpenAiLlmClient.php#L156),
[GeminiLlmClient.php:90](../app/Services/Ai/Providers/GeminiLlmClient.php#L90),
[LlmInference.php:30](../app/Services/Ai/LlmInference.php#L30).

Adapter melempar exception saat refusal/safety block sebelum mengembalikan
`LlmResponse`. `LlmInference` hanya dapat mengamati usage dari respons yang
berhasil dikembalikan; pada exception tanpa observation, ia mencatat `null`.
`usable()` juga dapat melempar setelah usage selesai diparse, sehingga angka
yang sudah tersedia tetap hilang.

Reproduksi menjalankan orchestrator dengan HTTP fake:

| Respons | Usage yang diberikan | Hasil run |
|---|---:|---|
| OpenAI refusal | 110 token | failed; total 0; measured calls 0; unknown |
| Gemini SAFETY | 100 token | failed; total 0; measured calls 0; unknown |
| OpenAI content kosong, finish stop | 110 token | failed; total 0; measured calls 0; unknown |

Ketiganya mencatat satu model call. Klasifikasi failure benar; kehilangan
pengukuran terjadi di batas adapter provider, sebelum observation oleh decorator.
Audit hanya membuktikan hilangnya angka yang diberikan provider, bukan nominal
tagihan atau aturan billing refusal setiap provider.

Arah patch: parse dan laporkan usage sekali per transport attempt sebelum
validasi content/refusal. Gunakan observer yang sudah tersedia atau exception
dengan metadata usage yang diproses oleh boundary inference. Pastikan wrapper
tidak mencatat attempt yang sama dua kali dan cancellation tetap tidak menerima
jawaban yang terlambat.

## AI-L04 — Objek usage rusak menghasilkan measured zero

Lokasi: [OpenAiLlmClient.php:136](../app/Services/Ai/Providers/OpenAiLlmClient.php#L136),
[GeminiLlmClient.php:121](../app/Services/Ai/Providers/GeminiLlmClient.php#L121),
[AiTokenAccounting.php:25](../app/Services/Ai/AiTokenAccounting.php#L25).

Syarat parsing hanya memastikan usage merupakan array tidak kosong. Counter
yang tidak ditemukan diberi nilai 0. DTO usage yang tidak null kemudian dihitung
sebagai measured model call.

Dengan jawaban valid dan `usage: {"unexpected": true}` atau padanan
`usageMetadata`, kedua provider menyelesaikan run dengan `total_tokens = 0`,
`measured_model_calls = 1`, dan status `complete`. Fixture tidak menyediakan
counter token apa pun; status lengkap tersebut tidak didukung data.

Arah patch: gunakan parser usage bersama dengan mapping field per provider.
Validasi kehadiran counter yang memadai, tipe integer, dan nilai nonnegatif;
bedakan measured zero yang sah dari metadata yang tidak diketahui atau parsial.
Metadata usage yang buruk tidak perlu membuang jawaban yang valid. Terapkan
bersama AI-L05 agar parsing dan observation mempunyai satu kontrak yang konsisten.

## Bukti verifikasi dan batas audit

Probe PHP sementara:
`storage/framework/testing/AiLatentAuditProbeTest.php`.
Probe JavaScript sementara:
`storage/framework/testing/ai-latent-audit.mjs`.
Keduanya berada di area storage yang diabaikan Git, di luar suite rutin.

```shell
php vendor/phpunit/phpunit/phpunit storage/framework/testing/AiLatentAuditProbeTest.php
node --test storage/framework/testing/ai-latent-audit.mjs
```

- PHP: **8 probe lulus, 31 assertions**, termasuk kontrol ambiguitas parsial.
- JavaScript: **1 probe lulus**, termasuk workaround terminal cancellation.
- Assertions pada probe memeriksa perilaku cacat yang terjadi saat ini; lulus
  berarti reproduksi berhasil, bukan bahwa defect sudah diperbaiki.
- PHP memakai SQLite `:memory:` dan HTTP fake. Tidak ada request provider nyata,
  pembayaran API, perubahan data workspace nyata, atau deployment.
- Tidak ada benchmark contention MySQL/Redis atau browser end-to-end pada audit ini.

Kandidat legacy compute job tanpa `claimToken` tidak dimasukkan sebagai bug:
payload buatan memang dapat memicu uninitialized property, tetapi riwayat file
yang tersedia sudah memiliki properti tersebut sejak penambahan job. Belum ada
bukti bahwa payload demikian dapat dihasilkan oleh versi proyek yang pernah
dijalankan.

Audit meninjau jalur lifecycle run, delegation science/coding, context assembly,
resolusi tool milik pengguna, observation provider, dan recovery browser. Daftar
ini bukan klaim bahwa seluruh kemungkinan bug AI telah tereliminasi.
