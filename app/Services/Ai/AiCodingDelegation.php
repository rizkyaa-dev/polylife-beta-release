<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\AiDesignIntent;
use Illuminate\Support\Facades\Validator;

/** Internal model-to-model capability; never a workspace write action. */
final class AiCodingDelegation
{
    public const TOOL_NAME = 'delegate_code_generation';

    private const LANGUAGE_PATTERN = '^[a-zA-Z][a-zA-Z0-9_+#-]*$';

    public function __construct(private readonly AiCodingBriefBuilder $builder) {}

    public function instruction(): string
    {
        return <<<'PROMPT'

[DELEGASI CODING WAJIB]
Anda menangani percakapan, klarifikasi, dan spesifikasi; coding agent menangani implementasi.
Untuk membuat, mengubah, memperbaiki/debug, atau refactor kode dalam bahasa APAPUN, panggil delegate_code_generation. Jangan menulis implementasi sendiri.
Putuskan berdasarkan MAKNA pesan dan konteks percakapan, bukan keyword. Contoh: "pengen bikin web statis buat porto" dan follow-up "stand alone aja gasi" merupakan permintaan implementasi.
Gabungkan kebutuhan relevan yang telah disepakati dari percakapan pada cabang ini ke brief lengkap. Follow-up pendek tetap mengacu pada proyek terkait; perpindahan topik eksplisit tidak mewarisi kebutuhan proyek lama.
Untuk pertanyaan konsep, diskusi ide, struktur folder, atau penjelasan kode tanpa permintaan implementasi, jawab biasa. Klarifikasi hanya jika informasi penting benar-benar kurang; gunakan asumsi wajar untuk detail opsional.
Brief bukan source code, tidak boleh berisi secret atau salinan data workspace yang tidak diperlukan.
Requirements memuat kebutuhan produk dan kualitas dasar yang mendukungnya (responsif, terbaca, keyboard/focus, feedback kontrol). Jangan mengubah asumsi menjadi fitur wajib. Toggle tema, filter, dependency dan workflow baru hanya jika diminta atau disepakati.
Tulis acceptance criteria dari kebutuhan tersebut, bukan dari wishlist fitur. Pertahankan scope saat merevisi; klarifikasi hanya keputusan penting, bukan wizard desain.
Pertahankan fakta eksplisit seperti nama brand. Data yang belum diberikan (identitas, alamat, jam, fasilitas, klaim produk) memakai placeholder singkat atau contoh berlabel dekat konten, bukan laporan proses pembuatan halaman. Jangan mewajibkan kontak aktif ke nomor rekaan. Pilihan kreatif desain boleh; mengarang fakta pengguna/usahanya tidak.
Untuk UI, isi design_intent dari MAKNA tujuan, tugas utama, audiens, perangkat dan lama pemakaian dalam konteks. Nilai unknown sah; jangan menebak demografi, brand atau preferensi warna. Catat pilihan yang belum didukung konteks dalam assumptions, bukan requirements.
Pilih goal (showcase/conversion/reading/productivity/information), interaction, session, density dan expression yang relevan. Warna mengikuti brand/preferensi atau arah yang dipilih secara sadar, bukan stereotip audiens. accent_hex hanya jika ada warna konkret enam digit.
Tulis visual_direction konkret: jangkar konten, hierarki, komposisi, karakter tipografi dan penggunaan aksen; bukan hanya "modern/profesional". Fakta audiens yang belum diketahui tetap unknown, tetapi agent boleh memilih arah kreatif yang koheren dari tujuan konten. Nyatakan itu sebagai pilihan, bukan preferensi pengguna. Jangan otomatis memilih abu-abu/restrained karena pengguna tidak memberi brand.
Penyempurnaan presentasi boleh mencakup variasi komposisi, treatment aset, hover/focus, feedback serta transisi singkat yang mendukung kebutuhan. JavaScript native/inline boleh untuk interaksi yang diperlukan, bukan dependency eksternal dan bukan fitur tambahan dengan sendirinya. Jangan wajibkan golden ratio, 60-30-10 atau animasi. Revisi kecil tetap mempertahankan desain sumber.
visual_direction hanya memiliki keys style, colors, layout; tiap field maksimal 300 karakter. Gunakan 1-2 kalimat padat, bukan spesifikasi panjang. Setiap item requirements/acceptance_criteria maksimal 500 karakter.
Pisahkan refinement desain dari kemampuan produk baru di SELURUH brief. Jangan menyisipkan filter proyek, theme switching atau workflow baru ke arah visual/asumsi saat tidak diminta. Color_mode unknown tidak menambah theme switching; gunakan satu mode yang sesuai arah terpilih. Kualitas bukan sinonim jumlah fitur maupun sedikitnya kode.
Jika merevisi kode yang sudah ada, set source_message_id ke ID pesan asisten sumber pada daftar artefak yang disediakan sistem. Jangan mengarang ID.
Untuk kalkulator/simulasi yang memakai hasil delegate_science_problem, kirim science_step_id yang diberikan tool atau daftar kontrak pada cabang ini. Jangan menyalin ulang persamaan/satuan sebagai pengganti kontrak rujukan; backend mengirim kontrak aslinya kepada coder. Jangan mengarang ID.
Selesaikan tool workspace lain terlebih dahulu. Delegasi coding harus menjadi satu-satunya tool call dalam respons tersebut dan merupakan hasil akhir turn, bukan bahan untuk ditulis ulang oleh Anda.
PROMPT;
    }

    /** Enforce the boundary for substantial implementations, not folder trees or short examples. */
    public function containsImplementation(?string $content): bool
    {
        preg_match_all('/```([a-zA-Z0-9_+#-]+)[^\r\n]*\R(.*?)```/s', $content ?? '', $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            if (in_array(strtolower($block[1]), ['text', 'txt', 'plaintext', 'markdown', 'md', 'log', 'json', 'yaml'], true)) {
                continue;
            }
            if (mb_strlen($block[2]) >= 800 || preg_match('/<!doctype\s+html|<html\b/i', $block[2]) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function declaration(): array
    {
        $list = ['type' => 'array', 'minItems' => 1, 'maxItems' => 16,
            'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500]];
        $visualText = ['type' => 'string', 'maxLength' => 300];

        return [
            'name' => self::TOOL_NAME,
            'description' => 'Delegate any code creation, modification, debugging or refactoring to an isolated coding agent. Include a complete contextual implementation brief.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'language' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32,
                        'pattern' => self::LANGUAGE_PATTERN,
                        'description' => 'One primary language identifier, e.g. html, javascript, python, c++ or c#. Any language is allowed. Put supporting languages in files/requirements, not a slash-separated language list.'],
                    'runtime' => ['type' => 'string', 'enum' => ['browser', 'server', 'cli', 'mobile', 'library']],
                    'files' => array_replace($list, ['maxItems' => 8, 'items' => ['type' => 'string', 'maxLength' => 160]]),
                    'requirements' => $list,
                    'acceptance_criteria' => $list,
                    'visual_direction' => ['type' => 'object', 'properties' => [
                        'style' => $visualText, 'colors' => $visualText, 'layout' => $visualText,
                    ], 'additionalProperties' => false],
                    'runnable' => ['type' => 'boolean'],
                    'design_intent' => AiDesignIntent::declaration(),
                    'source_message_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Optional existing assistant artifact ID for revisions.'],
                    'science_step_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Scientific contract step ID returned by delegate_science_problem or listed as available on this branch.'],
                ],
                'required' => ['language', 'runtime', 'files', 'requirements', 'acceptance_criteria'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function brief(array $arguments, string $prompt, AiCodingInstructionRouter $router): AiCodingBrief
    {
        Validator::make($arguments, array_merge([
            'language' => ['required', 'string', 'max:32', 'regex:/'.self::LANGUAGE_PATTERN.'/D'],
            'runtime' => ['required', 'in:browser,server,cli,mobile,library'],
            'files' => ['required', 'array', 'min:1', 'max:8'], 'files.*' => ['string', 'max:160'],
            'requirements' => ['required', 'array', 'min:1', 'max:16'], 'requirements.*' => ['required', 'string', 'max:500'],
            'acceptance_criteria' => ['required', 'array', 'min:1', 'max:16'], 'acceptance_criteria.*' => ['required', 'string', 'max:500'],
            'visual_direction' => ['sometimes', 'array:style,colors,layout'], 'visual_direction.*' => ['string', 'max:300'],
            'runnable' => ['sometimes', 'boolean'],
            'source_message_id' => ['sometimes', 'integer', 'min:1'],
            'science_step_id' => ['sometimes', 'integer', 'min:1'],
        ], AiDesignIntent::validationRules()))->validate();

        return $this->builder->fromResponse(json_encode($arguments, JSON_THROW_ON_ERROR), $prompt, $router->forLanguage($arguments['language']));
    }
}
