<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiRunStateManager;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiRunCancelledException;
use App\Services\Ai\Science\ScienceContractStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiScienceDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_science_agent_is_isolated_and_solver_result_is_available_to_main(): void
    {
        $client = new class implements LlmClientInterface
        {
            public array $requests = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'tools', 'systemInstruction', 'options');

                return match (count($this->requests)) {
                    1 => new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve x+2y=5, 3x+4y=11.'])]),
                    2 => new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Unknown order: x,y; dimensionless linear equations.', 'assumptions' => [], 'units' => ['dimensionless'], 'solver' => 'linear_system', 'inputs' => ['matrix' => [[1, 2], [3, 4]], 'rhs' => [5, 11]]])),
                    3 => new LlmResponse('x=1, y=2; residual checked, physical formulation not independently verified.'),
                    default => throw new \LogicException('Unbounded inference'),
                };
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $result = app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Solve these two equations with residual verification.');
        $this->assertSame('completed', $result['run']->status);
        $this->assertCount(3, $client->requests);
        $this->assertSame([], $client->requests[1]['tools']);
        $this->assertCount(1, $client->requests[1]['messages']);
        $envelope = json_decode($client->requests[1]['messages'][0]->content, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame('Solve these two equations with residual verification.', $envelope['request']['original_problem']);
        $this->assertNotNull($client->requests[1]['options']->ensureActive);
        $step = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail();
        $this->assertSame('numerically_checked', $step->private_payload['result']['verification']['status']);
        $this->assertSame('unverified', $step->private_payload['model_verification']);
        $toolMessage = $client->requests[2]['messages'][2];
        $this->assertSame('tool', $toolMessage->role);
        $this->assertSame('linear_system', $toolMessage->toolResult['result']['solver']);
    }

    private function client(array $responses): object
    {
        $client = new class($responses) implements LlmClientInterface
        {
            public array $requests = [];

            public function __construct(private array $responses) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'tools', 'systemInstruction', 'options');
                $response = array_shift($this->responses) ?? throw new \LogicException('Unexpected inference');
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return is_callable($response) ? $response() : $response;
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    private function sciencePlan(): LlmResponse
    {
        return new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Unknown order x,y; dimensionless.',
            'assumptions' => [], 'units' => ['dimensionless'], 'solver' => 'linear_system',
            'inputs' => ['matrix' => [[1, 2], [3, 4]], 'rhs' => [5, 11]]]));
    }

    private function codingCall(int $id): LlmResponse
    {
        return new LlmResponse(null, [new LlmToolCall('code', 'delegate_code_generation', [
            'language' => 'html', 'runtime' => 'browser', 'files' => ['calculator.html'],
            'requirements' => ['Implement a linear-system calculator from the scientific contract.'],
            'acceptance_criteria' => ['Reference inputs reproduce the computed solution.'],
            'science_step_id' => $id,
        ])]);
    }

    public function test_same_turn_scientific_calculator_gets_backend_contract_not_main_copy(): void
    {
        $client = $this->client([
            new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve linear equations.'])]),
            $this->sciencePlan(),
            fn () => $this->codingCall(AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail()->id),
            new LlmResponse("```html\n<html><body>Calculator</body></html>\n```"),
        ]);
        app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Make a scientific calculator for these linear equations.');
        $payload = $client->requests[3]['messages'][0]->content;
        $this->assertStringContainsString('science_contract', $payload);
        $this->assertStringContainsString('reference_result', $payload);
        $this->assertStringContainsString('matrix', $payload);
        $this->assertStringContainsString('[SCIENTIFIC CONTRACT]', $client->requests[3]['systemInstruction']);
        $this->assertSame([], $client->requests[3]['tools']);
        $coder = AiChatRunStep::where('tool_name', 'delegate_code_generation')->firstOrFail();
        $this->assertSame([5, 11], $coder->private_payload['science_contract']['inputs']['rhs']);
    }

    public function test_cross_user_scientific_contract_is_rejected_without_coder_inference(): void
    {
        $this->client([
            new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve linear equations.'])]),
            $this->sciencePlan(), new LlmResponse('Computed result.'),
        ]);
        app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Solve equations.');
        $id = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail()->id;
        $client = $this->client([$this->codingCall($id), new LlmResponse('Scientific reference unavailable on this branch.')]);
        app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Build calculator.');
        $this->assertCount(2, $client->requests);
        $step = AiChatRunStep::where('tool_name', 'delegate_code_generation')->firstOrFail();
        $this->assertSame('failed', $step->status);
        $this->assertArrayHasKey('science_step_id', $step->private_payload['errors']);
    }

    public function test_follow_up_calculator_resolves_only_the_owned_ancestor_contract(): void
    {
        $this->client([new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve equations.'])]),
            $this->sciencePlan(), new LlmResponse('Computed result.')]);
        $user = User::factory()->create();
        $first = app(AiAgentOrchestrator::class)->handle($user, 'Solve equations.');
        $id = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail()->id;
        $client = $this->client([$this->codingCall($id), new LlmResponse("```html\n<html>Calculator</html>\n```")]);
        app(AiAgentOrchestrator::class)->handle($user, 'Turn the result into a calculator.', $first['session']->id);
        $this->assertCount(2, $client->requests);
        $this->assertStringContainsString('Scientific contract step IDs available on this branch: '.$id, $client->requests[0]['systemInstruction']);
        $envelope = json_decode(str_replace(['<delegated_coding_request>', '</delegated_coding_request>'], '',
            $client->requests[1]['messages'][0]->content), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame($id, $envelope['science_contract']['step_id']);
        $this->assertSame([5, 11], $envelope['science_contract']['inputs']['rhs']);
    }

    public function test_edit_does_not_inherit_a_scientific_contract_from_the_discarded_sibling(): void
    {
        $this->client([new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve equations.'])]),
            $this->sciencePlan(), new LlmResponse('Computed result.')]);
        $user = User::factory()->create();
        $first = app(AiAgentOrchestrator::class)->handle($user, 'Solve equations.');
        $id = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail()->id;
        $client = $this->client([$this->codingCall($id), new LlmResponse('Contract unavailable on this branch.')]);
        app(AiAgentOrchestrator::class)->edit($user, $first['user_message']->id, 'Build a different calculator.');
        $this->assertCount(2, $client->requests);
        $this->assertStringNotContainsString('Scientific contract step IDs available', $client->requests[0]['systemInstruction']);
        $this->assertSame('failed', AiChatRunStep::where('tool_name', 'delegate_code_generation')->firstOrFail()->status);
    }

    public function test_cancellation_during_planning_never_publishes_a_solver_result(): void
    {
        $client = $this->client([new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve equations.'])]),
            function () {
                app(AiRunStateManager::class)->fail(AiChatRun::query()->where('status', 'running')->firstOrFail(), 'user_cancelled', false);

                return $this->sciencePlan();
            }]);
        try {
            app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Solve equations.');
            $this->fail('Cancelled work must not publish.');
        } catch (AiRunCancelledException) {
            $this->assertCount(2, $client->requests);
        }
        $step = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail();
        $this->assertSame('failed', $step->status);
        $this->assertArrayHasKey('science_request', $step->private_payload);
        $this->assertArrayNotHasKey('result', $step->private_payload);
        $this->assertDatabaseMissing('ai_chat_messages', ['role' => 'assistant']);
        $this->assertDatabaseHas('ai_chat_runs', ['status' => 'failed', 'error_code' => 'user_cancelled']);
    }

    public function test_provider_timeout_retains_only_encrypted_request_not_partial_science(): void
    {
        $this->client([new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'PRIVATE_SCIENCE_MARKER'])]),
            AiProviderException::timeout()]);
        try {
            app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Solve equations.');
            $this->fail('Expected timeout.');
        } catch (AiProviderException $exception) {
            $this->assertSame('provider_timeout', $exception->errorCode);
        }
        $step = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail();
        $this->assertSame('PRIVATE_SCIENCE_MARKER', $step->private_payload['science_request']['problem']);
        $this->assertStringNotContainsString('PRIVATE_SCIENCE_MARKER', $step->getRawOriginal('private_payload'));
        $this->assertStringNotContainsString('PRIVATE_SCIENCE_MARKER', json_encode($step->public_metadata));
        $this->assertArrayNotHasKey('result', $step->private_payload);
        $this->assertDatabaseHas('ai_chat_runs', ['status' => 'failed', 'retryable' => true]);
    }

    public function test_clarification_and_unverified_payloads_cannot_be_used_as_computed_contracts(): void
    {
        $this->client([new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve equations.'])]),
            $this->sciencePlan(), new LlmResponse('Computed result.')]);
        app(AiAgentOrchestrator::class)->handle(User::factory()->create(), 'Solve equations.');
        $step = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail();
        $payload = $step->private_payload;
        $payload['result']['verification']['status'] = 'unverified';
        $step->update(['private_payload' => $payload]);
        $this->expectException(ValidationException::class);
        app(ScienceContractStore::class)->resolve($step->id, collect([$step->id => $step]));
    }
}
