<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiInferencePolicy;
use App\Services\Ai\Enums\ThinkingEffort;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiInferencePolicyTest extends TestCase
{
    #[DataProvider('efforts')]
    public function test_effort_controls_runtime_and_output_budget(
        ThinkingEffort $effort,
        int $runTimeout,
        int $requestTimeout,
        int $maxTokens
    ): void {
        $policy = new AiInferencePolicy;
        $options = $policy->requestOptions($effort, 999);

        $this->assertSame($runTimeout, $policy->runTimeoutSeconds($effort));
        $this->assertSame($requestTimeout, $options->timeoutSeconds);
        $this->assertSame($maxTokens, $options->maxOutputTokens);
    }

    public static function efforts(): array
    {
        return [
            'off' => [ThinkingEffort::Off, 60, 30, 2048],
            'low' => [ThinkingEffort::Low, 90, 45, 4096],
            'high' => [ThinkingEffort::High, 180, 75, 8192],
            'max' => [ThinkingEffort::Max, 300, 150, 16384],
        ];
    }

    public function test_request_timeout_never_exceeds_remaining_run_budget(): void
    {
        $options = (new AiInferencePolicy)->requestOptions(ThinkingEffort::Max, 17);

        $this->assertSame(17, $options->timeoutSeconds);
    }
}
