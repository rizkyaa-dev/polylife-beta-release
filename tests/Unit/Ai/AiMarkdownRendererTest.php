<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiMarkdownRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiMarkdownRendererTest extends TestCase
{
    public function test_large_result_numbers_use_indonesian_grouping_without_rounding(): void
    {
        $html = app(AiMarkdownRenderer::class)->render('12 × 871421 − 7846523 ÷ 2 × 0.8 = **7318442,8**');
        $this->assertStringContainsString('<strong>7.318.442,8</strong>', $html);
        $this->assertStringContainsString('871.421', $html);
        $this->assertStringContainsString('7.846.523', $html);

        $html = app(AiMarkdownRenderer::class)->render('Hasil: -12345678901234567890.000000000000000001.');
        $this->assertStringContainsString('-12.345.678.901.234.567.890,000000000000000001.', $html);
    }

    public function test_numeric_display_keeps_grouped_values_code_math_and_identifiers_intact(): void
    {
        $markdown = <<<'MD'
Sudah diformat: **7.318.442,8**. Tahun 2026, tanggal 2026-10-14, rasio 0.002.

Notasi ilmiah 7318442e-3, kode ABC7318442, padding 0007318442, serial 7318442-12345.

{"value":7318442.8} dan [100000,200000].

https://example.com/?value=7318442

`7318442.8` dan \(7318442.8\).

[7318442](https://example.com/7318442)

```js
const result = 7318442.8;
```
MD;
        $html = app(AiMarkdownRenderer::class)->render($markdown);
        $this->assertStringContainsString('<strong>7.318.442,8</strong>', $html);
        $this->assertStringContainsString('Tahun 2026, tanggal 2026-10-14, rasio 0.002.', $html);
        $this->assertStringContainsString('7318442e-3', $html);
        $this->assertStringContainsString('ABC7318442', $html);
        $this->assertStringContainsString('0007318442', $html);
        $this->assertStringContainsString('7318442-12345', $html);
        $this->assertStringContainsString('7318442.8}', $html);
        $this->assertStringContainsString('[100000,200000]', $html);
        $this->assertStringContainsString('https://example.com/?value=7318442', $html);
        $this->assertStringContainsString('<code>7318442.8</code>', $html);
        $this->assertStringContainsString('>7318442.8</span>', $html);
        $this->assertStringContainsString('href="https://example.com/7318442">7318442</a>', $html);
        $this->assertStringContainsString('const result = 7318442.8;', $html);
    }

    public function test_large_quantities_in_tables_receive_the_same_display_format(): void
    {
        $html = app(AiMarkdownRenderer::class)->render("| Nilai |\n|---|\n| 7318442,80 |\n| 1234567890 |\n");
        $this->assertStringContainsString('<td>7.318.442,80</td>', $html);
        $this->assertStringContainsString('<td>1.234.567.890</td>', $html);
    }

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

    public function test_science_table_decimals_use_local_separators_without_changing_technical_text(): void
    {
        $html = app(AiMarkdownRenderer::class)->render("| Nilai |\n|---|\n| 59.4855 mg/L |\n| −179.317 mg·menit/L |\n| 0.216216 |\n| 7.318.442,8 |\n\nVersi 1.2.3, `0.216216` dan \\(0.216216\\). Notasi 1.23e-4.");
        $this->assertStringContainsString('59,4855 mg/L', $html);
        $this->assertStringContainsString('−179,317 mg·menit/L', $html);
        $this->assertStringContainsString('<td>0,216216</td>', $html);
        $this->assertStringContainsString('7.318.442,8', $html);
        $this->assertStringContainsString('1.2.3', $html);
        $this->assertStringContainsString('<code>0.216216</code>', $html);
        $this->assertStringContainsString('1.23e-4', $html);
    }

    public function test_decimal_localization_preserves_ambiguous_grouped_currency(): void
    {
        $html = app(AiMarkdownRenderer::class)->render("| Nilai |\n|---|\n| Rp 7.318 |\n| 150.000 |\n| 0.002 mg/L |\n| 18.312 menit |\n");
        $this->assertStringContainsString('Rp 7.318', $html);
        $this->assertStringContainsString('<td>150.000</td>', $html);
        $this->assertStringContainsString('0,002 mg/L', $html);
        $this->assertStringContainsString('18,312 menit', $html);
    }
}
