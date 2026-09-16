<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\AiCodingRoute;

final class AiCodingInstructionRouter
{
    /** @var array<string, list<string>> */
    private const LANGUAGE_ALIASES = [
        'html' => ['html', 'website', 'web page', 'landing page', 'standalone'],
        'css' => ['css', 'stylesheet', 'tailwind'],
        'javascript' => ['javascript', 'js', 'node.js', 'nodejs'],
        'typescript' => ['typescript', 'ts'],
        'php' => ['php', 'laravel'],
        'python' => ['python', 'django', 'flask', 'fastapi'],
        'java' => ['java', 'spring boot'],
        'csharp' => ['c#', 'csharp', '.net', 'asp.net'],
        'cpp' => ['c++', 'cpp'],
        'c' => ['bahasa c', ' c '],
        'go' => ['golang', ' go '],
        'rust' => ['rust'],
        'dart' => ['dart', 'flutter'],
        'sql' => ['sql', 'mysql', 'postgresql', 'sqlite'],
        'bash' => ['bash', 'shell script', 'powershell'],
        'json' => ['json'],
    ];

    /** @var list<string> */
    private const CODING_SIGNALS = [
        'buatkan coding', 'buat coding', 'bikin coding', 'buatkan kode', 'buat kode',
        'bikin kode', 'tulis kode', 'source code', 'code untuk', 'implementasikan',
        'debug kode', 'perbaiki kode', 'refactor', 'script', 'program', 'codingan',
        'standalone',
    ];

    /** @var list<string> */
    private const GENERATION_SIGNALS = [
        'buat', 'bikin', 'tulis', 'hasilkan', 'generate', 'implementasi', 'perbaiki',
        'debug', 'refactor', 'contoh',
    ];

    public function instructionsFor(string $prompt): ?string
    {
        return $this->route($prompt)?->instructions;
    }

    public function route(string $prompt): ?AiCodingRoute
    {
        $normalized = ' '.mb_strtolower(trim($prompt)).' ';
        $language = $this->detectLanguage($normalized);

        $hasCodingIntent = $this->hasCodingIntent($normalized);
        if (! $hasCodingIntent && ($language === null || ! $this->hasGenerationIntent($normalized))) {
            return null;
        }

        $language ??= 'text';

        return $this->forLanguage($language);
    }

    public function forLanguage(string $language): AiCodingRoute
    {
        $language = strtolower(trim($language));
        $language = match ($language) {
            'js' => 'javascript', 'ts' => 'typescript', 'py' => 'python',
            'c#' => 'csharp', 'c++' => 'cpp', 'sh' => 'bash',
            default => $language,
        };
        if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $language) !== 1) {
            $language = 'text';
        }
        $languageRules = $this->languageRules($language);

        $instructions = <<<PROMPT
[MODE ARTIFAK KODE: {$language}]
- Jawab permintaan coding dengan penjelasan singkat lalu fenced code block berlabel bahasa `{$language}`.
- Setiap file harus berada dalam fenced code block terpisah. Tulis nama file pada teks tepat sebelum blok jika ada lebih dari satu file.
- Kode harus lengkap, konsisten, dan langsung dapat disalin. Jangan memotong bagian kode dengan placeholder seperti "lanjutkan sendiri".
- Jangan memasukkan rahasia, API key, kredensial, atau data pribadi ke kode.
- Jangan mengklaim kode sudah dijalankan atau diuji kecuali memang ada hasil eksekusi yang tersedia.
{$languageRules}
PROMPT;

        return new AiCodingRoute($language, $instructions);
    }

    private function hasCodingIntent(string $prompt): bool
    {
        foreach (self::CODING_SIGNALS as $signal) {
            if (str_contains($prompt, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function hasGenerationIntent(string $prompt): bool
    {
        foreach (self::GENERATION_SIGNALS as $signal) {
            if ($this->containsTerm($prompt, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function detectLanguage(string $prompt): ?string
    {
        foreach (self::LANGUAGE_ALIASES as $language => $aliases) {
            foreach ($aliases as $alias) {
                if ($this->containsTerm($prompt, trim($alias))) {
                    return $language;
                }
            }
        }

        return null;
    }

    private function containsTerm(string $prompt, string $term): bool
    {
        return preg_match(
            '/(?<![\\pL\\pN])'.preg_quote($term, '/').'(?![\\pL\\pN])/iu',
            $prompt
        ) === 1;
    }

    private function languageRules(string $language): string
    {
        return match ($language) {
            'html' => '- Untuk standalone, hasilkan satu dokumen HTML lengkap; CSS inline dan JavaScript native/inline sesuai kebutuhan interaksi, tanpa library eksternal kecuali diminta. JS tidak wajib, tetapi offline/standalone bukan larangan JS atau state lokal. Utamakan SVG/data URI untuk aset; gambar HTTPS boleh digunakan bila membutuhkan fotografi nyata dan wajib memiliki alt text serta fallback visual. Pastikan responsif dan dapat dibuka langsung di browser.',
            'css' => '- Gunakan CSS valid, responsif, dan sertakan konteks selector/markup minimum yang dibutuhkan.',
            'javascript' => '- Gunakan JavaScript modern tanpa dependency kecuali diminta. Tangani error dan hindari API browser yang tidak aman.',
            'typescript' => '- Gunakan TypeScript strict dengan tipe eksplisit pada boundary publik dan hindari `any` tanpa alasan.',
            'php' => '- Gunakan PHP modern, strict typing bila berupa file penuh, validasi input, dan escaping output.',
            'python' => '- Gunakan Python modern, type hints pada boundary publik, serta penanganan error yang jelas.',
            'java' => '- Gunakan Java modern, tipe yang jelas, resource management yang aman, dan struktur class yang dapat dikompilasi.',
            'csharp' => '- Gunakan C# modern dengan nullable reference types, async/await yang benar, dan disposal resource yang aman.',
            'cpp' => '- Gunakan C++ modern dengan RAII, standard library, dan hindari raw ownership serta operasi memori yang tidak aman.',
            'c' => '- Gunakan C standar dengan pemeriksaan batas buffer, return code, dan pengelolaan memori yang eksplisit.',
            'go' => '- Gunakan Go idiomatis, context pada operasi I/O, error wrapping yang informatif, dan pastikan goroutine tidak bocor.',
            'rust' => '- Gunakan Rust idiomatis, manfaatkan ownership/type system, dan hindari `unsafe` kecuali benar-benar diperlukan.',
            'dart' => '- Gunakan Dart null-safe, async yang konsisten, dan pisahkan state dari presentasi untuk contoh Flutter.',
            'sql' => '- Gunakan query terparameterisasi pada contoh aplikasi dan jangan menyusun SQL dari input mentah.',
            'bash' => '- Gunakan mode aman, quote variabel, dan hindari perintah destruktif tanpa pemeriksaan target.',
            'json' => '- Keluarkan JSON valid tanpa komentar atau trailing comma.',
            default => '- Ikuti idiom, formatter, dan praktik keamanan baku bahasa tersebut.',
        };
    }
}
