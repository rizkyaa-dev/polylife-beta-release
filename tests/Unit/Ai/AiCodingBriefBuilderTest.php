<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCodingBriefBuilder;
use App\Services\Ai\DTOs\AiCodingRoute;
use PHPUnit\Framework\TestCase;

class AiCodingBriefBuilderTest extends TestCase
{
    public function test_it_normalizes_untrusted_planner_output(): void
    {
        $content = json_encode([
            'runtime' => 'browser',
            'files' => ['index.html', '../secret.env', '/absolute.php', 'assets/app js.js'],
            'requirements' => ['Responsive', '', ['invalid']],
            'acceptance_criteria' => ['Berjalan tanpa build tool'],
            'visual_direction' => ['Style' => 'Warm editorial', 'invalid' => ['nested']],
            'runnable' => true,
        ], JSON_THROW_ON_ERROR);

        $brief = (new AiCodingBriefBuilder)->fromResponse(
            $content,
            'Buat coffee shop',
            new AiCodingRoute('html', 'HTML rules')
        );

        $this->assertSame('html', $brief->language);
        $this->assertSame('browser', $brief->runtime);
        $this->assertSame(['index.html', 'assets/app-js.js'], $brief->files);
        $this->assertSame(['Responsive'], $brief->requirements);
        $this->assertSame(['style' => 'Warm editorial'], $brief->visualDirection);
        $this->assertTrue($brief->runnable);
    }

    public function test_it_builds_a_safe_fallback_when_planner_json_is_invalid(): void
    {
        $brief = (new AiCodingBriefBuilder)->fromResponse(
            'Saya tidak mengeluarkan JSON.',
            'Buat parser Python sederhana',
            new AiCodingRoute('python', 'Python rules')
        );

        $this->assertSame('python', $brief->language);
        $this->assertSame('server', $brief->runtime);
        $this->assertSame(['code.py'], $brief->files);
        $this->assertSame(['Buat parser Python sederhana'], $brief->requirements);
        $this->assertFalse($brief->runnable);
    }
}
