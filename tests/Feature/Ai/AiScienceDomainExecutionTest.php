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
use Tests\TestCase;

class AiScienceDomainExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_preparation_survives_browser_handoff_without_trusting_client_evidence(): void
    {
        Queue::fake();
        config(['services.ai_science_browser_enabled' => true, 'services.ai_queue_connection' => 'database']);
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/science-rc-domain.json')), true, 32, JSON_THROW_ON_ERROR);
        $user = User::factory()->create(['account_status' => 'active', 'role' => 'user', 'email_verified_at' => now()]);
        UserAiAssistant::create(['user_id' => $user->id, 'assistant_name' => 'Test', 'personality_tone' => 'friendly_peer', 'thinking_effort' => 'low']);
        $client = new class($fixture) implements LlmClientInterface
        {
            public array $requests = [];

            public function __construct(private readonly array $fixture) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'tools', 'systemInstruction', 'options');

                return match (count($this->requests)) {
                    1 => new LlmResponse(null, [new LlmToolCall('rc1', 'delegate_science_problem', ['problem' => 'Compute the RC transient.'])]),
                    2 => new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Declared ideal RC network.',
                        'assumptions' => [], 'units' => ['V'], 'solver' => 'ode_ivp', 'domain_model' => $this->fixture['domain_model'],
                        'model_source' => 'FORGED_SOURCE', 'model_verification' => 'physically_verified'])),
                    3 => new LlmResponse('Declared RC structure checked; physical interpretation remains unverified.'),
                    default => throw new \LogicException('Domain preparation must not introduce extra model calls.'),
                };
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $orchestrator = app(AiAgentOrchestrator::class);
        $turn = $orchestrator->enqueue($user, $fixture['source'], requestId: (string) Str::uuid(), scienceClient: true);
        $suspended = $orchestrator->processRun($turn['run']->id);
        $this->assertSame('awaiting_science', $suspended['phase']);
        $ticket = AiScienceExecution::firstOrFail();
        $plan = $ticket->private_payload['plan'];
        $this->assertSame($fixture['source'], $plan['model_source']);
        $this->assertSame('declared_structure_checked', $plan['model_evidence']['status']);
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $this->assertArrayNotHasKey('model_source', $claim);
        $this->assertArrayNotHasKey('model_evidence', $claim);
        $this->assertStringNotContainsString('R1=1 kohm', json_encode($claim));
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'result' => [
            'status' => 'computed', 'final_state' => [999, 999], 'model_verification' => 'physically_verified',
            'model_evidence' => ['status' => 'everything_verified'],
        ]]);
        $broker->execute($ticket->id);
        $authoritative = $ticket->fresh()->private_payload['result'];
        $this->assertSame('unverified', $authoritative['model_verification']);
        $this->assertSame($plan['model_evidence'], $authoritative['model_evidence']);
        $this->assertEqualsWithDelta(8.324986336363539, $authoritative['result']['final_state'][0], 1e-7);
        $completed = $orchestrator->processRun($turn['run']->id);
        $this->assertSame('completed', $completed['run']->status);
        $this->assertCount(3, $client->requests);
        $history = $client->requests[2]['messages'];
        $publicResult = $history[count($history) - 1]->toolResult['result'];
        $this->assertArrayNotHasKey('model_source', $publicResult);
        $this->assertSame('declared_structure_checked', $publicResult['model_evidence']['status']);
        $step = AiChatRunStep::where('tool_name', 'delegate_science_problem')->firstOrFail();
        $contract = app(ScienceContractStore::class)->resolve($step->id, collect([$step->id => $step]));
        $this->assertSame($plan['model_evidence'], $contract['model_evidence']);
        $this->assertSame($fixture['domain_model'], $contract['domain_model']);
        $this->assertArrayNotHasKey('model_source', $contract);
        $payload = $step->private_payload;
        $payload['model_evidence'] = ['status' => 'stale_or_forged_verified'];
        $step->private_payload = $payload;
        $freshContract = app(ScienceContractStore::class)->resolve($step->id, collect([$step->id => $step]));
        $this->assertSame($plan['model_evidence'], $freshContract['model_evidence']);
        $payload['solver_inputs']['derivatives'][0] = ['op' => 'const', 'value' => 999];
        $step->private_payload = $payload;
        $this->expectException(ValidationException::class);
        app(ScienceContractStore::class)->resolve($step->id, collect([$step->id => $step]));
    }
}
