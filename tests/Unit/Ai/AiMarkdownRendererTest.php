<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiMarkdownRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiMarkdownRendererTest extends TestCase
{
    #[Test]
    public function it_formats_markdown_and_removes_unsafe_html_and_links(): void
    {
        $html = app(AiMarkdownRenderer::class)->render(<<<'MARKDOWN'
**Tebal**

- Satu
- Dua

<script>alert('xss')</script>

[Tautan berbahaya](javascript:alert('xss'))

![Pelacak](https://example.com/pixel.png)
MARKDOWN);

        $this->assertStringContainsString('<strong>Tebal</strong>', $html);
        $this->assertStringContainsString('<li>Satu</li>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
