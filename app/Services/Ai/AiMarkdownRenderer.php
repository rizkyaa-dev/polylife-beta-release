<?php

namespace App\Services\Ai;

use App\Services\Ai\Markdown\MathExtension;
use Illuminate\Support\Str;

class AiMarkdownRenderer
{
    public function __construct(private readonly AiCodeArtifactRenderer $codeArtifactRenderer) {}

    public function render(?string $markdown): string
    {
        if ($markdown === null || $markdown === '') {
            return '';
        }

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'max_delimiters_per_line' => 100,
        ], [new MathExtension]);

        $sanitized = strip_tags($html, '<p><br><strong><em><ul><ol><li><blockquote><pre><code><a><del><h1><h2><h3><h4><hr><table><thead><tbody><tr><th><td><span>');
        $sanitized = preg_replace('/(<table\b[^>]*>.*?<\/table>)/s', '<div class="ai-table-scroll" tabindex="0" role="region" aria-label="Tabel jawaban">$1</div>', $sanitized) ?? $sanitized;

        return $this->codeArtifactRenderer->decorate($sanitized);
    }
}
