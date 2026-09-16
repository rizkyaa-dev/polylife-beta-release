<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCodingInstructionRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiCodingInstructionRouterTest extends TestCase
{
    #[DataProvider('languagePrompts')]
    public function test_it_routes_coding_prompts_to_language_specific_instructions(
        string $prompt,
        string $language,
        string $expectedRule
    ): void {
        $instructions = app(AiCodingInstructionRouter::class)->instructionsFor($prompt);

        $this->assertNotNull($instructions);
        $this->assertStringContainsString("[MODE ARTIFAK KODE: {$language}]", $instructions);
        $this->assertStringContainsString($expectedRule, $instructions);
    }

    /** @return array<string, array{string, string, string}> */
    public static function languagePrompts(): array
    {
        return [
            'standalone html' => ['Buat HTML standalone untuk coffee shop', 'html', 'gambar HTTPS boleh digunakan'],
            'strict typescript' => ['Bikin codingan TypeScript untuk parser', 'typescript', 'TypeScript strict'],
            'secure sql' => ['Tulis kode SQL untuk pencarian user', 'sql', 'query terparameterisasi'],
            'safe shell' => ['Buat shell script untuk backup', 'bash', 'quote variabel'],
            'idiomatic go' => ['Buat program Go untuk worker pool', 'go', 'goroutine tidak bocor'],
            'memory safe cpp' => ['Buat contoh C++ untuk membaca file', 'cpp', 'RAII'],
        ];
    }

    public function test_it_does_not_change_unrelated_conversation(): void
    {
        $this->assertNull(app(AiCodingInstructionRouter::class)->instructionsFor('Besok saya ada jadwal apa?'));
        $this->assertNull(app(AiCodingInstructionRouter::class)->instructionsFor('Jelaskan sejarah bahasa Java.'));
    }

    public function test_it_does_not_confuse_json_with_javascript(): void
    {
        $instructions = app(AiCodingInstructionRouter::class)->instructionsFor('Buat contoh JSON untuk profil mahasiswa.');

        $this->assertNotNull($instructions);
        $this->assertStringContainsString('[MODE ARTIFAK KODE: json]', $instructions);
    }
}
