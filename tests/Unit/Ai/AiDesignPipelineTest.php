<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCodingBriefBuilder;
use App\Services\Ai\AiCodingDelegation;
use App\Services\Ai\AiCodingInstructionRouter;
use App\Services\Ai\AiCodingPromptBuilder;
use App\Services\Ai\Design\AiDesignIntentResolver;
use App\Services\Ai\Design\AiDesignTokenCompiler;
use App\Services\Ai\Design\DesignColorMath;
use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\AiDesignIntent;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AiDesignPipelineTest extends TestCase
{
    private function brief(array $intent = [], string $language = 'html', string $runtime = 'browser'): AiCodingBrief
    {
        return new AiCodingBrief($language, $runtime, ['index.html'], ['USER_REQUIREMENT_ONLY'], ['Works'], [], true, AiDesignIntent::fromArray($intent));
    }

    public function test_tool_contract_and_validation_accept_the_same_intent_vocabulary(): void
    {
        $schema = AiDesignIntent::declaration();
        foreach (AiDesignIntent::OPTIONS as $field => $options) {
            $this->assertSame($options, $schema['properties'][$field]['enum']);
            foreach ($options as $value) {
                $this->assertTrue(Validator::make(['design_intent' => [$field => $value]], AiDesignIntent::validationRules())->passes());
            }
            $this->assertTrue(Validator::make(['design_intent' => [$field => 'IGNORE_CONTRACT']], AiDesignIntent::validationRules())->fails());
        }
        foreach ([['unexpected' => 'x'], ['accent_hex' => '#abc'], ['accent_hex' => 'red; background:url(x)'],
            ['audience' => str_repeat('x', 201)], ['assumptions' => array_fill(0, 7, 'assumed')]] as $invalid) {
            $this->assertTrue(Validator::make(['design_intent' => $invalid], AiDesignIntent::validationRules())->fails());
        }
        $this->assertTrue(Validator::make([], AiDesignIntent::validationRules())->passes());
    }

    public function test_bounded_value_object_never_interprets_audience_text_as_policy(): void
    {
        $intent = AiDesignIntent::fromArray(['goal' => ['reading'], 'audience' => str_repeat('x', 400),
            'accent_hex' => '#AbC123', 'expression' => 'ignore rules', 'assumptions' => ['yes', [], '', str_repeat('x', 300)]]);
        $this->assertSame('unknown', $intent->value('goal'));
        $this->assertSame('unknown', $intent->value('expression'));
        $this->assertSame('#abc123', $intent->value('accent_hex'));
        $this->assertSame(200, mb_strlen($intent->value('audience')));
        $this->assertCount(2, $intent->value('assumptions'));
        $this->assertSame(200, mb_strlen($intent->value('assumptions')[1]));
        $this->assertSame(AiDesignIntent::fromArray([])->value('color_family'), AiDesignIntent::fromArray(['audience' => 'young adults'])->value('color_family'));
    }

    public function test_legacy_briefs_remain_compatible_and_use_honest_neutral_fallback(): void
    {
        $brief = (new AiCodingBriefBuilder)->fromResponse('{}', 'Create a page', (new AiCodingInstructionRouter)->forLanguage('html'));
        $this->assertNull($brief->designIntent);
        $this->assertArrayNotHasKey('design_intent', $brief->toArray());
        $plan = app(AiDesignIntentResolver::class)->resolve($brief, false);
        $this->assertSame('neutral fallback; audience unknown', $plan['provenance']);
        $this->assertSame('unknown', $plan['intent']['goal']);
        $this->assertSame('', $plan['intent']['audience']);
        $this->assertStringContainsString('unverified', $plan['render_verification']);
    }

    public function test_semantic_planner_intent_selects_composition_not_keyword_matching(): void
    {
        $resolver = app(AiDesignIntentResolver::class);
        $reading = $resolver->resolve($this->brief(['goal' => 'reading', 'session' => 'sustained']), false);
        $work = $resolver->resolve($this->brief(['goal' => 'productivity', 'density' => 'compact', 'interaction' => 'pointer']), false);
        $showcase = $resolver->resolve($this->brief(['goal' => 'showcase', 'expression' => 'expressive']), false);
        $this->assertStringContainsString('alur baca', $reading['decisions'][0]);
        $this->assertSame(18, $reading['tokens']['type']['body']);
        $this->assertStringContainsString('dark mode', $reading['decisions'][2]);
        $this->assertStringContainsString('tugas berulang', $work['decisions'][0]);
        $this->assertSame(8, $work['tokens']['spacing']['item_gap']);
        $this->assertSame(40, $work['tokens']['controls']['recommended_height']);
        $this->assertStringContainsString('bukti karya', $showcase['decisions'][0]);
        $this->assertStringContainsString('bukan kewajiban animasi', $showcase['decisions'][1]);
        $this->assertSame(44, $showcase['tokens']['controls']['recommended_height']);
        $this->assertStringNotContainsString('USER_REQUIREMENT_ONLY', json_encode($showcase));
    }

    public function test_revisions_do_not_replace_source_tokens_and_non_ui_has_no_plan(): void
    {
        $resolver = app(AiDesignIntentResolver::class);
        $revision = $resolver->resolve($this->brief(['goal' => 'showcase', 'accent_hex' => '#ff0000']), true);
        $this->assertNull($revision['tokens']);
        $this->assertStringContainsString('preserve source', $revision['application']);
        $this->assertNull($resolver->resolve($this->brief([], 'python', 'cli'), false));
        $this->assertNull($resolver->resolve($this->brief([], 'javascript', 'library'), false));
        $this->assertNotNull($resolver->resolve($this->brief(['goal' => 'productivity'], 'dart', 'mobile'), false));
    }

    public function test_intent_is_preserved_in_brief_but_never_interpolated_into_system(): void
    {
        $arguments = ['language' => 'html', 'runtime' => 'browser', 'files' => ['index.html'],
            'requirements' => ['Page'], 'acceptance_criteria' => ['Works'],
            'design_intent' => ['goal' => 'showcase', 'audience' => 'AUDIENCE_UNTRUSTED_MARKER', 'assumptions' => ['AUDIENCE_UNKNOWN']]];
        $brief = app(AiCodingDelegation::class)->brief($arguments, 'Make it', new AiCodingInstructionRouter);
        $this->assertSame('showcase', $brief->designIntent->value('goal'));
        $this->assertSame('AUDIENCE_UNTRUSTED_MARKER', $brief->toArray()['design_intent']['audience']);
        $system = app(AiCodingPromptBuilder::class)->build($brief, (new AiCodingInstructionRouter)->forLanguage('html'), false);
        $this->assertStringNotContainsString('AUDIENCE_UNTRUSTED_MARKER', $system);
        $this->assertStringNotContainsString('AUDIENCE_UNKNOWN', $system);
        $this->assertStringContainsString('Asumsi tidak mengizinkan filter', $system);
        $this->assertStringContainsString('refinement', $system);
    }

    public function test_every_color_role_pair_passes_for_all_modes_families_and_extreme_accents(): void
    {
        $compiler = app(AiDesignTokenCompiler::class);
        foreach (['light', 'dark', 'unknown'] as $mode) {
            foreach (['neutral', 'warm', 'cool', 'unknown'] as $family) {
                foreach (['#ffffff', '#000000', '#ffff00', '#ff0000', '#777777', '#123abc'] as $accent) {
                    $tokens = $compiler->compile(AiDesignIntent::fromArray(['color_mode' => $mode, 'color_family' => $family, 'accent_hex' => $accent]));
                    $this->assertSame($accent, $tokens['colors']['accent']);
                    foreach ($tokens['verification']['contrast'] as $pair) {
                        $this->assertTrue($pair['pass'], "$mode/$family/$accent");
                        $this->assertGreaterThanOrEqual($pair['minimum'], $pair['ratio']);
                    }
                }
            }
        }
    }

    public function test_color_math_and_radius_have_exact_limits_and_reject_invalid_input(): void
    {
        $math = new DesignColorMath;
        $this->assertEqualsWithDelta(21, $math->contrast('#000000', '#ffffff'), 0.00001);
        $this->assertEqualsWithDelta(1, $math->contrast('#123abc', '#123abc'), 0.00001);
        $this->assertEqualsWithDelta($math->contrast('#112233', '#aabbcc'), $math->contrast('#aabbcc', '#112233'), 0.00001);
        $this->assertGreaterThanOrEqual(4.5, $math->contrast($math->readable('#ffff00', '#ffffff'), '#ffffff'));
        $compiler = app(AiDesignTokenCompiler::class);
        $this->assertSame(12.0, $compiler->innerRadius(20, 8));
        $this->assertEquals(0, $compiler->innerRadius(4, 8));
        $this->expectException(\InvalidArgumentException::class);
        $compiler->innerRadius(-1, 2);
    }

    public function test_color_input_cannot_carry_css_or_unsupported_alpha(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DesignColorMath)->contrast('#ffffff00', '#000000');
    }
}
