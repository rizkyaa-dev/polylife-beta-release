<?php

namespace App\Services\Ai;

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
        ]);

        $sanitized = strip_tags($html, '<p><br><strong><em><ul><ol><li><blockquote><pre><code><a><del><h1><h2><h3><h4><hr>');

        return $this->codeArtifactRenderer->decorate($sanitized);
    }
}
