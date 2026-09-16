<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Design\AiDesignArtifactEvaluator;
use PHPUnit\Framework\TestCase;

class AiDesignArtifactEvaluatorTest extends TestCase
{
    public function test_it_reports_static_defects_without_claiming_render_verification(): void
    {
        $code = "```html\n<!doctype html><html><body><a href='#missing'>Missing</a><a href='#'>Fake</a><a href=''>Empty</a><a href='javascript:void(0)'>Fake</a><img src='x'><p id='a'></p><p id='a'></p></body></html>\n```";
        $report = (new AiDesignArtifactEvaluator)->evaluate($code);
        $this->assertSame('unverified', $report['render']);
        $this->assertSame(1, $report['checks']['broken_fragment_links']['count']);
        $this->assertSame(3, $report['checks']['placeholder_links']['count']);
        $this->assertSame(1, $report['checks']['images_without_alt']['count']);
        $this->assertSame(1, $report['checks']['duplicate_ids']['count']);
    }

    public function test_real_fragment_targets_mailto_and_decorative_alt_pass(): void
    {
        $report = (new AiDesignArtifactEvaluator)->evaluate("```html\n<html><body><a href='#caf%C3%A9'>Go</a><section id='café'></section><a href='mailto:me@example.com'>Mail</a><img src='data:image/svg+xml,x' alt=''></body></html>\n```");
        foreach ($report['checks'] as $check) {
            $this->assertSame('pass', $check['status']);
        }
        $this->assertSame('unverified', $report['render']);
    }

    public function test_non_documents_multiple_documents_and_oversize_are_unverified_not_fatal(): void
    {
        $evaluator = new AiDesignArtifactEvaluator;
        foreach (["```python\nprint('x')\n```", "```html\n<button>OK</button>\n```",
            "```html\n<html></html>\n```\n```html\n<html></html>\n```", str_repeat('x', 180001)] as $content) {
            $report = $evaluator->evaluate($content);
            $this->assertSame([], $report['checks']);
            $this->assertSame('unverified', $report['render']);
        }
    }

    public function test_generated_scripts_are_parsed_but_never_executed_and_libxml_mode_is_restored(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            $report = (new AiDesignArtifactEvaluator)->evaluate("```html\n<html><script>throw new Error('MUST_NOT_EXECUTE'); fetch('https://example.com')</script></html>\n```");
            $this->assertSame('unverified', $report['render']);
            $this->assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
