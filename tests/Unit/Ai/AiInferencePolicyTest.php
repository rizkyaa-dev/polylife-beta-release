<?php

namespace Tests\Unit\Ai;

use App\Jobs\ProcessAiChatRun;
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

    public function test_long_form_response_can_raise_output_budget_without_changing_effort(): void
    {
        $options = (new AiInferencePolicy)->requestOptions(ThinkingEffort::Low, 90, 16384);

        $this->assertSame(ThinkingEffort::Low, $options->thinkingEffort);
        $this->assertSame(16384, $options->maxOutputTokens);
        $this->assertSame(45, $options->timeoutSeconds);
    }

    public function test_coder_budget_is_separate_but_never_extends_remaining_run_time(): void
    {
        $policy = new AiInferencePolicy;
        $this->assertSame(85, $policy->requestOptions(ThinkingEffort::Low, 85, 16384, true)->timeoutSeconds);
        $this->assertSame(17, $policy->requestOptions(ThinkingEffort::Low, 17, 16384, true)->timeoutSeconds);
        $this->assertSame(90, $policy->runTimeoutSeconds(ThinkingEffort::Low));
        $this->assertSame(1, $policy->requestOptions(ThinkingEffort::Max, 1)->timeoutSeconds);
    }

    public function test_coding_budgets_preserve_effort_and_remain_bounded(): void
    {
        $policy = new AiInferencePolicy;
        foreach ([[ThinkingEffort::Off, 120, 180], [ThinkingEffort::Low, 180, 240],
            [ThinkingEffort::High, 240, 360], [ThinkingEffort::Max, 360, 480]] as [$effort, $request, $run]) {
            $options = $policy->requestOptions($effort, 999, 16384, true);
            $this->assertSame($request, $options->timeoutSeconds);
            $this->assertSame($run, $policy->runTimeoutSeconds($effort, true));
            $this->assertSame($effort, $options->thinkingEffort);
            $this->assertSame(17, $policy->requestOptions($effort, 17, 16384, true)->timeoutSeconds);
        }
        $this->assertGreaterThan(480, (new ProcessAiChatRun(1))->timeout);
        foreach (['database', 'redis', 'beanstalkd'] as $connection) {
            $this->assertGreaterThan(510, config("queue.connections.{$connection}.retry_after"));
        }
    }
}
