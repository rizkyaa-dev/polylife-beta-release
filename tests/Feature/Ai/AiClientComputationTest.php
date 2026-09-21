<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatRunStep;
use App\Models\AiScienceExecution;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Science\ScienceContractStore;
use App\Services\Ai\Science\ScienceExecutionBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiClientComputationTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $enabled = true, string $kernelStatus = 'unsupported', string $clientStatus = 'ready', bool $localOnly = false, bool $browser = true): array
    {
        Queue::fake();
        config(['services.ai_science_browser_enabled' => true, 'services.ai_science_dynamic_enabled' => $enabled,
            'services.ai_science_kernel_enabled' => ! $localOnly,
            'services.ai_science_capability_telemetry_enabled' => true, 'services.ai_queue_connection' => 'database']);
        $user = User::factory()->create(['account_status' => 'active', 'role' => 'user', 'email_verified_at' => now()]);
        UserAiAssistant::create(['user_id' => $user->id, 'assistant_name' => 'Test', 'personality_tone' => 'friendly_peer', 'thinking_effort' => 'low']);
        $client = new class($enabled, $kernelStatus, $clientStatus, $localOnly) implements LlmClientInterface
        {
            public array $requests = [];

            public function __construct(private bool $enabled, private string $kernelStatus, private string $clientStatus, private bool $localOnly) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'systemInstruction');
                $call = count($this->requests);
                if ($call === 1) {
                    return new LlmResponse(null, [new LlmToolCall('local1', 'delegate_science_problem', ['problem' => 'Compute reactor volume with D=0.08 m, L=6 m.'])]);
                }
                if ($call === 2 && ! $this->localOnly) {
                    return new LlmResponse(json_encode(['status' => $this->kernelStatus, 'model' => 'No registered geometry solver', 'assumptions' => [], 'units' => [],
                        ...($this->kernelStatus === 'needs_clarification' ? ['question' => 'Please supply length.'] : ['capability_gaps' => ['unsupported_domain']])]));
                }
                if ($call === ($this->localOnly ? 2 : 3) && $this->enabled && ($this->localOnly || $this->kernelStatus === 'unsupported')) {
                    return new LlmResponse(json_encode(['status' => $this->clientStatus, 'model' => 'Cylinder geometry only; not a reactor simulation.', 'assumptions' => [], 'units' => ['m3'],
                        ...($this->clientStatus === 'ready' ? ['program' => ['source' => 'function compute(i){const V=Math.PI*i.D*i.D*i.L/4;return {values:{V},checks:[{name:"positive volume",passed:V>0}]}}',
                            'inputs' => ['D' => .08, 'L' => 6], 'checks' => ['positive volume']]] : ['question' => 'Please supply transport data.'])]));
                }

                return new LlmResponse('Result is client-reported and not independently verified.');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $orchestrator = app(AiAgentOrchestrator::class);
        $turn = $orchestrator->enqueue($user, 'Compute reactor volume with D=0.08 m, L=6 m.', requestId: (string) Str::uuid(), scienceClient: $browser);
        $processed = $orchestrator->processRun($turn['run']->id);

        return compact('user', 'client', 'orchestrator', 'turn', 'processed');
    }

    public function test_local_only_mode_skips_registered_planning_and_server_replay(): void
    {
        $s = $this->scenario(localOnly: true);
        $this->assertSame('awaiting_science', $s['processed']['phase']);
        $this->assertCount(2, $s['client']->requests);
        $this->assertStringContainsString('isolated client-computation', $s['client']->requests[1]['systemInstruction']);
        $ticket = AiScienceExecution::firstOrFail();
        $this->assertSame('kernel_disabled', $ticket->private_payload['plan']['routing_reason']);
        $this->assertArrayNotHasKey('kernel_fallback', $ticket->private_payload['plan']);
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $this->assertTrue($claim['auto_execute']);
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'],
            'result' => ['values' => ['V' => .03], 'checks' => [['name' => 'positive volume', 'passed' => true]]]]);
        $broker->execute($ticket->id);
        $this->assertSame('client_computed', $ticket->fresh()->private_payload['result']['status']);
        $s['orchestrator']->processRun($s['turn']['run']->id);
        $this->assertCount(3, $s['client']->requests);
        $this->assertDatabaseCount('ai_science_capability_gaps', 0);
    }

    public function test_local_only_mode_without_browser_never_falls_back_to_server(): void
    {
        $s = $this->scenario(localOnly: true, browser: false);
        $this->assertSame('completed', $s['processed']['run']->status);
        $this->assertCount(2, $s['client']->requests);
        $this->assertDatabaseCount('ai_science_executions', 0);
        $history = $s['client']->requests[1]['messages'];
        $this->assertSame('unsupported', $history[count($history) - 1]->toolResult['result']['status']);
    }

    public function test_zero_click_policy_is_rendered_and_can_be_revoked_before_claim(): void
    {
        $s = $this->scenario(localOnly: true);
        $this->actingAs($s['user'])->get(route('ai.workspace', ['session' => $s['turn']['session']->id]))
            ->assertOk()->assertSee('data-science-client-auto-execute="true"', false);
        config(['services.ai_science_client_auto_execute' => false]);
        $claim = app(ScienceExecutionBroker::class)->claim(AiScienceExecution::firstOrFail(), (string) Str::uuid());
        $this->assertFalse($claim['auto_execute']);
    }

    public static function clientFailureReasons(): array
    {
        return array_map(fn (string $reason): array => [$reason], ['cancelled', 'timeout', 'unavailable', 'execution_failed', 'syntax_error', 'invalid_input']);
    }

    #[DataProvider('clientFailureReasons')]
    public function test_local_only_failure_resumes_without_any_kernel_fallback(string $reason): void
    {
        $s = $this->scenario(localOnly: true);
        $ticket = AiScienceExecution::firstOrFail();
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'failure' => $reason]);
        $broker->execute($ticket->id);
        $result = $ticket->fresh()->private_payload['result'];
        $this->assertSame('unsupported', $result['status']);
        $this->assertNull($result['result']);
        $this->assertNull($result['execution']['authoritative_runner']);
        $this->assertSame('client_failed', \App\Models\AiChatRunStep::findOrFail($ticket->step_id)->public_metadata['execution_phase']);
        $s['orchestrator']->processRun($s['turn']['run']->id);
        $this->assertCount(3, $s['client']->requests);
        $this->assertSame('completed', $s['turn']['run']->fresh()->status);
    }

    public function test_client_fallback_resumes_once_without_server_execution_or_trust_promotion(): void
    {
        $s = $this->scenario();
        $this->assertSame('awaiting_science', $s['processed']['phase']);
        $broker = app(ScienceExecutionBroker::class);
        $ticket = AiScienceExecution::firstOrFail();
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $this->assertSame('client_script', $claim['execution_mode']);
        $this->assertArrayNotHasKey('cache_scope', $claim);
        $this->assertStringContainsString('function compute', $claim['program']['source']);
        $body = ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'result' => ['values' => ['V' => .030159289474462014],
            'checks' => [['name' => 'positive volume', 'passed' => true]], 'verification' => 'physically_verified']];
        $broker->submit($ticket, $body);
        $broker->submit($ticket, $body);
        $broker->execute($ticket->id);
        $result = $ticket->fresh()->private_payload['result'];
        $this->assertSame('client_computed', $result['status']);
        $this->assertNull($result['execution']['authoritative_runner']);
        $this->assertSame('client_reported_only', $result['result']['verification']['status']);
        $this->assertSame('unverified', $result['model_verification']);
        $completed = $s['orchestrator']->processRun($s['turn']['run']->id);
        $this->assertSame('completed', $completed['run']->status);
        $this->assertCount(4, $s['client']->requests);
        $this->actingAs($s['user'])->get(route('ai.workspace', ['session' => $s['turn']['session']->id]))
            ->assertOk()->assertSee('Skrip dan hasil lokal')->assertSee('function compute');
        $history = $s['client']->requests[3]['messages'];
        $tool = $history[count($history) - 1]->toolResult['result'];
        $this->assertArrayNotHasKey('program', $tool);
        $this->assertSame('client_reported_only', $tool['result']['verification']['status']);
        $this->assertDatabaseHas('ai_science_capability_gaps', ['capability' => 'unsupported_domain', 'unsupported_count' => 1, 'prepared_count' => 1, 'client_result_count' => 1]);
        $step = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail();
        $this->assertSame('client_script', $step->public_metadata['execution_mode']);
        $this->expectException(ValidationException::class);
        app(ScienceContractStore::class)->resolve($step->id, collect([$step->id => $step]));
    }

    public function test_disabled_fallback_does_not_make_an_extra_inference_call(): void
    {
        $s = $this->scenario(false);
        $this->assertSame('completed', $s['processed']['run']->status);
        $this->assertCount(3, $s['client']->requests);
        $this->assertDatabaseCount('ai_science_executions', 0);
    }

    public function test_missing_input_never_enters_client_fallback(): void
    {
        $s = $this->scenario(true, 'needs_clarification');
        $this->assertCount(3, $s['client']->requests);
        $this->assertSame('completed', $s['processed']['run']->status);
        $this->assertDatabaseCount('ai_science_executions', 0);
        $this->assertDatabaseCount('ai_science_capability_gaps', 0);
    }

    public function test_dynamic_planner_can_request_clarification_without_executing(): void
    {
        $s = $this->scenario(true, 'unsupported', 'needs_clarification');
        $this->assertCount(4, $s['client']->requests);
        $this->assertSame('completed', $s['processed']['run']->status);
        $this->assertDatabaseCount('ai_science_executions', 0);
    }

    public function test_lost_client_lease_resumes_with_no_numerical_result(): void
    {
        $s = $this->scenario();
        $ticket = AiScienceExecution::firstOrFail();
        $this->travelTo($ticket->lease_expires_at->addSecond());
        app(ScienceExecutionBroker::class)->execute($ticket->id);
        $result = $ticket->fresh()->private_payload['result'];
        $this->assertSame('unsupported', $result['status']);
        $this->assertNull($result['result']);
        $this->assertSame('unavailable', $result['execution']['client_failure']);
        $this->assertSame('completed', $s['orchestrator']->processRun($s['turn']['run']->id)['run']->status);
    }
}
