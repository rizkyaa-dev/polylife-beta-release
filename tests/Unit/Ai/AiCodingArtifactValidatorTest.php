<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCodingArtifactValidator;
use App\Services\Ai\AiMarkdownRenderer;
use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\LlmResponse;
use Tests\TestCase;

class AiCodingArtifactValidatorTest extends TestCase
{
    public function test_documentation_files_in_a_code_brief_keep_their_own_language(): void
    {
        $brief = new AiCodingBrief('python', 'cli', ['main.py', 'README.md', '.env.example'], [], [], [], false);
        $this->assertSame([], app(AiCodingArtifactValidator::class)->errors(new LlmResponse("```py\nprint(1)\n```\n```markdown\n# Usage\n```\n```ini\nAPP_MODE=local\n```"), $brief));
    }

    public function test_incomplete_mismatched_and_wrong_language_files_are_rejected(): void
    {
        $brief = new AiCodingBrief('html', 'browser', ['index.html', 'style.css'], [], [], [], true);
        foreach (["```html\n<html></html>", "```html\n<html></html>\n```", "```html\n<html></html>\n```\n```python\nprint(1)\n```"] as $source) {
            $this->assertNotEmpty(app(AiCodingArtifactValidator::class)->errors(new LlmResponse($source), $brief));
        }
    }

    public function test_tilde_fences_receive_the_same_static_checks_and_valid_native_anchors_pass(): void
    {
        $brief = new AiCodingBrief('html', 'browser', ['landing.html'], [], [], [], true);
        $validator = app(AiCodingArtifactValidator::class);
        $this->assertArrayHasKey('broken_fragment_links', $validator->errors(new LlmResponse("~~~html\n<html><a href=\"#missing\">Broken</a></html>\n~~~"), $brief));
        $this->assertSame([], $validator->errors(new LlmResponse("~~~~html\n<html><a href=\"#top\">Top</a><a name=\"legacy\"></a><a href=\"#legacy\">Legacy</a></html>\n~~~~~"), $brief));
    }

    public function test_multi_file_artifacts_use_brief_filenames_without_a_partial_run_button(): void
    {
        $html = app(AiMarkdownRenderer::class)->render("```html\n<html></html>\n```\n```css\nbody {color:red}\n```", ['files' => ['site/index.html', 'style.css'], 'execution' => ['runnable' => true]]);
        $this->assertStringContainsString('data-code-filename="index.html"', $html);
        $this->assertStringContainsString('data-code-filename="style.css"', $html);
        $this->assertStringNotContainsString('data-code-run aria-label', $html);
    }
}
