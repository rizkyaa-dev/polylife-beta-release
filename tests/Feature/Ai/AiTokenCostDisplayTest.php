<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;

class AiTokenCostDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai_provider' => 'deepseek', 'services.deepseek.model' => 'deepseek-flash',
            'ai_cost.enabled' => true, 'ai_cost.profile.usd_to_idr' => 17937,
            'ai_cost.profile.exchange_rate_date' => '2026-10-01']);
    }

    private function renderCounter(): string
    {
        return view('ai.partials.token-counter', [
            'latestRunTokens' => ['prompt' => 12918, 'completion' => 173, 'total' => 13091, 'status' => 'complete'],
            'sessionTokens' => ['prompt' => 25836, 'completion' => 346, 'total' => 26182, 'status' => 'partial'],
        ])->render();
    }

    public function test_deepseek_counter_exposes_reference_tariff_and_separate_cost_targets(): void
    {
        $html = $this->renderCounter();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        $counter = $xpath->query('//*[@data-ai-token-counter]')->item(0);
        $profile = json_decode($counter->getAttribute('data-cost-profile'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('DeepSeek 4.1 Flash', $profile['model']);
        $this->assertEquals(0.30, $profile['input_usd_per_million']);
        $this->assertEquals(1.20, $profile['output_usd_per_million']);
        $this->assertEquals(17937, $profile['usd_to_idr']);
        $this->assertSame(1, $xpath->query('//*[@data-ai-token-cost-latest]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-ai-token-cost-session]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-ai-token-detail-in]/../*[@data-ai-token-cost-input]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-ai-token-detail-out]/../*[@data-ai-token-cost-output]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-ai-token-session-total]/../*[@data-ai-token-cost-session]')->length);
        $this->assertStringNotContainsString('Estimasi biaya sesi</span>', $html);
        $this->assertSame('Tercatat', trim($xpath->query('//*[@data-ai-token-measurement]')->item(0)->textContent));
        $this->assertSame('≥26,182 tok', trim($xpath->query('//*[@data-ai-token-session-total]')->item(0)->textContent));
        $this->assertStringContainsString('Rp17.937/US$', $html);
        $this->assertStringContainsString('tanpa diskon cache', $html);
        $this->assertStringContainsString('bukan tagihan', $html);
    }

    public function test_feature_flag_removes_all_cost_markup_without_removing_tokens(): void
    {
        config(['ai_cost.enabled' => false]);
        $html = $this->renderCounter();
        $this->assertStringNotContainsString('data-cost-profile', $html);
        $this->assertStringNotContainsString('data-ai-token-cost-', $html);
        $this->assertStringContainsString('data-ai-token-detail-total', $html);
    }

    public function test_other_providers_and_models_never_use_flash_pricing(): void
    {
        foreach ([['gemini', 'deepseek-flash'], ['openai', 'deepseek-flash'], ['deepseek', 'deepseek-v4-pro']] as [$provider, $model]) {
            config(['services.ai_provider' => $provider, 'services.deepseek.model' => $model]);
            $this->assertStringNotContainsString('data-cost-profile', $this->renderCounter());
        }
    }

    public function test_exchange_rate_is_configurable_and_supported_flash_aliases_keep_the_display(): void
    {
        config(['ai_cost.profile.usd_to_idr' => 18000, 'ai_cost.profile.exchange_rate_date' => '2026-10-02',
            'services.deepseek.model' => 'deepseek-v4-flash']);
        $html = $this->renderCounter();
        $this->assertStringContainsString('Rp18.000/US$ (2026-10-02)', $html);
        $this->assertStringContainsString('data-cost-profile', $html);
    }
}
