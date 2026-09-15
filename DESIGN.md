# PolyLife Design Direction

## Identity

PolyLife adalah workspace pribadi mahasiswa. Antarmuka terasa tenang, teratur, dekat, dan dapat dipercaya tanpa kehilangan karakter akademik.

## Visual language

- Gunakan permukaan bersih dan kontras yang jelas agar percakapan tetap menjadi fokus utama.
- Indigo PolyLife menjadi aksen untuk pilihan aktif, fokus keyboard, dan tindakan utama. Aksen tidak dipakai sebagai dekorasi umum.
- Bentuk membulat membedakan kontrol interaktif dari bidang konten, tetapi tidak semua elemen dibuat seperti pil.
- Ikon harus menjelaskan tindakan. Sparkle hanya boleh bertahan sebagai satu motif asisten, bukan penanda untuk setiap fitur AI.
- Referensi produk lain digunakan untuk mempelajari pola interaksi, bukan untuk menyalin identitas visualnya.

## Typography and spacing

- Gunakan sans-serif aplikasi yang sudah ada karena keterbacaannya pada data, formulir, dan percakapan panjang.
- Spasi rapat pada kontrol dan lebih longgar di antara pesan untuk membedakan tindakan dari isi.
- Hierarki mengutamakan pesan, composer, lalu kontrol sekunder.

## Motion

Transisi hanya memberi umpan balik pada hover, fokus, pembukaan menu, dan perubahan state. Tidak ada animasi yang berjalan tanpa henti.

## Dials

ENERGY 1 / RHYTHM 1 / MOTION 1

## AI workspace decision

Kontrol thinking memakai satu tombol ringkas karena keputusan utamanya tetap menulis pesan. Pilihan effort muncul dalam popover hanya ketika diminta dan selalu menampilkan nilai aktif dengan teks.

Edit pesan memakai percabangan versi agar koreksi tidak memalsukan riwayat lama. Action row berada dekat bubble pengguna, terlihat saat hover/focus dan tetap dapat diketuk pada perangkat sentuh. Counter versi hanya muncul ketika memang ada lebih dari satu versi.

Transparansi agentic memakai satu disclosure ringkas sebelum jawaban. Reasoning publik dan aktivitas tool dibedakan lewat label serta marker fungsional; chain-of-thought mentah tidak menjadi konten antarmuka.
