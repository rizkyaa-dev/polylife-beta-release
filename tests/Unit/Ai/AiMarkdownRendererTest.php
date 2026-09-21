<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiMarkdownRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiMarkdownRendererTest extends TestCase
{
    public function test_tables_retain_their_semantic_columns(): void
    {
        $html = app(AiMarkdownRenderer::class)->render("| Reaction | A | B |\n|---|---|---|\n| R1 | -1 | -1 |");
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>A</th>', $html);
        $this->assertStringContainsString('<td>-1</td>', $html);
        $this->assertStringContainsString('ai-table-scroll', $html);
    }

    public function test_math_is_preserved_before_markdown_parsing_and_safely_escaped(): void
    {
        $html = app(AiMarkdownRenderer::class)->render(<<<'MD'
Inline $C_{i,p}$ and \(T_p\).

$$
\varepsilon_p\frac{\partial C_{i,p}}{\partial t}=D_{i,eff}
$$

\[a < b\]
MD);
        $this->assertSame(4, substr_count($html, 'data-ai-math='));
        $this->assertStringContainsString('C_{i,p}', $html);
        $this->assertStringContainsString('\\varepsilon_p\\frac', $html);
        $this->assertStringContainsString('a &lt; b', $html);
        $this->assertStringNotContainsString('<em>', $html);
    }

    public function test_math_does_not_execute_html_or_interpret_code_and_currency(): void
    {
        $html = app(AiMarkdownRenderer::class)->render(<<<'MD'
`$x$` and $10 and $20.

```js
const price = "$x$";
```

    $indented$

\$escaped$ and $unclosed

$\text{<img src=x onerror=alert(1)>}$

<span data-ai-math="inline" onclick="alert(1)">evil</span>
MD);
        $this->assertSame(1, substr_count($html, 'data-ai-math='));
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringContainsString('$10 and $20', $html);
    }

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
