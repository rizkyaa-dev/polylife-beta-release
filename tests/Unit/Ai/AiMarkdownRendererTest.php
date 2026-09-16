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

    public function test_it_turns_fenced_code_into_a_safe_downloadable_and_runnable_artifact(): void
    {
        $html = app(AiMarkdownRenderer::class)->render(<<<'MARKDOWN'
```html
<!doctype html>
<title>Demo</title>
<h1>Hello</h1>
```
MARKDOWN);

        $this->assertStringContainsString('data-code-artifact', $html);
        $this->assertStringContainsString('data-code-language="html"', $html);
        $this->assertStringContainsString('data-code-filename="code.html"', $html);
        $this->assertStringContainsString('data-code-copy', $html);
        $this->assertStringContainsString('data-code-download', $html);
        $this->assertStringContainsString('data-code-run', $html);
        $this->assertStringContainsString('&lt;h1&gt;Hello&lt;/h1&gt;', $html);
        $this->assertStringNotContainsString('<h1>Hello</h1>', $html);
    }

    public function test_non_browser_code_can_be_copied_and_downloaded_but_not_run(): void
    {
        $html = app(AiMarkdownRenderer::class)->render("```php\n<?php echo 'aman';\n```");

        $this->assertStringContainsString('data-code-filename="code.php"', $html);
        $this->assertStringContainsString('data-code-copy', $html);
        $this->assertStringContainsString('data-code-download', $html);
        $this->assertStringNotContainsString('<button type="button" data-code-run', $html);
    }
}
