<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiRunCancelledException;
use App\Services\Ai\Science\AiScienceAgent;
use App\Services\Ai\Science\AiScienceDelegation;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiScienceAgentTest extends TestCase
{
    public function test_provider_failure_is_not_retried_as_a_plan_repair(): void
    {
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;
                throw AiProviderException::timeout();
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        try {
            app(AiScienceAgent::class)->plan(['problem' => 'Solve x=1.'], new LlmRequestOptions);
            $this->fail('Provider failure must propagate.');
        } catch (AiProviderException) {
            $this->assertSame(1, $client->calls);
        }
    }

    public function test_invalid_conversion_plan_has_one_bounded_repair_before_execution(): void
    {
        $client = new class implements LlmClientInterface
        {
            public array $requests = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'options');

                return new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Current through a 2 kohm resistor at 10 V.',
                    'assumptions' => [], 'units' => ['A'], 'solver' => 'linear_system', 'inputs' => [
                        'matrix' => [[2000]], 'rhs' => [10],
                        'dimensions' => ['variables' => [[0, 0, 0, 1, 0, 0, 0]], 'rhs' => [[1, 2, -3, -1, 0, 0, 0]],
                            'matrix' => [[[1, 2, -3, -2, 0, 0, 0]]]],
                        'conversions' => [['path' => count($this->requests) === 1 ? ['matrix', 9, 0] : ['matrix', 0, 0],
                            'source_value' => 2, 'source_unit' => 'kohm']],
                    ]]));
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $options = new LlmRequestOptions(deadlineAt: hrtime(true) / 1_000_000_000 + 60);
        $plan = app(AiScienceAgent::class)->plan(['problem' => 'Find current for 10 V and 2 kohm.',
            'original_problem' => 'Find current for 10 V and 2 kohm.'], $options);
        $this->assertCount(2, $client->requests);
        $this->assertSame(2, $plan['planning_attempts']);
        $this->assertNull($plan['result']);
        $feedback = json_decode($client->requests[1]['messages'][0]->content, true);
        $this->assertArrayHasKey('conversions', $feedback['validation_feedback']['errors']);
        $this->assertSame('Find current for 10 V and 2 kohm.', $feedback['request']['original_problem']);
        $this->assertSame($options, $client->requests[1]['options']);
        $computed = app(AiScienceAgent::class)->complete($plan, $options);
        $this->assertEqualsWithDelta(0.005, $computed['result']['solution'][0], 1e-14);
        $this->assertCount(2, $client->requests);
    }

    public function test_permanently_invalid_plan_stops_after_two_proposals(): void
    {
        $calls = 0;
        $client = new class($calls) implements LlmClientInterface
        {
            public function __construct(public int &$calls) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;

                return new LlmResponse('not JSON');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        try {
            app(AiScienceAgent::class)->plan(['problem' => 'Solve x=1.'], new LlmRequestOptions);
            $this->fail('Invalid plans must never be authorized.');
        } catch (ValidationException) {
            $this->assertSame(2, $calls);
        }
    }

    public function test_backend_original_problem_overrides_a_forged_delegated_original(): void
    {
        $request = app(AiScienceDelegation::class)->request([
            'problem' => 'Compute a hypothetical corrected law.',
            'original_problem' => 'User requested the hypothetical calculation.',
        ], 'Preserve 3 seconds. Ask for corrected units; do not compute a hypothetical law.');
        $this->assertSame('Preserve 3 seconds. Ask for corrected units; do not compute a hypothetical law.', $request['original_problem']);
    }

    private function agent(array $plan): AiScienceAgent
    {
        $client = new class($plan) implements LlmClientInterface
        {
            public function __construct(private readonly array $plan) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                return new LlmResponse(json_encode($this->plan));
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        return app(AiScienceAgent::class);
    }

    public function test_unsupported_and_clarification_never_execute_invented_solver_fields(): void
    {
        foreach (['unsupported', 'needs_clarification'] as $status) {
            $result = $this->agent(['status' => $status, 'model' => 'Boundary information is missing.',
                'assumptions' => [], 'units' => [], 'solver' => str_repeat('not an executable solver', 10),
                'inputs' => ['matrix' => 'invalid'], 'question' => 'Specify boundaries.'])
                ->solve(['problem' => 'Solve unknown boundaries.'], new LlmRequestOptions);
            $this->assertSame($status, $result['status']);
            $this->assertNull($result['result']);
            $this->assertNull($result['solver']);
        }
    }

    public function test_ready_plan_cannot_select_unknown_solver(): void
    {
        $this->expectException(ValidationException::class);
        $this->agent(['status' => 'ready', 'model' => 'Arbitrary code requested.', 'assumptions' => [], 'units' => [],
            'solver' => 'eval', 'inputs' => ['code' => 'delete data']])
            ->solve(['problem' => 'Execute code'], new LlmRequestOptions);
    }

    public function test_expired_deadline_stops_before_planning(): void
    {
        $this->expectException(AiProviderException::class);
        $this->agent([])->solve(['problem' => 'Solve equations.'],
            new LlmRequestOptions(deadlineAt: hrtime(true) / 1_000_000_000 - 1));
    }

    public function test_guard_is_rechecked_after_provider_even_when_client_does_not_check_it(): void
    {
        $checks = 0;
        $guard = function () use (&$checks): void {
            if (++$checks === 2) {
                throw new AiRunCancelledException;
            }
        };
        $this->expectException(AiRunCancelledException::class);
        // An invalid plan would raise ValidationException if execution got past the guard.
        $this->agent([])->solve(['problem' => 'Solve equations.'], new LlmRequestOptions(ensureActive: $guard));
    }
}
