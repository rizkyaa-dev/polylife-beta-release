<?php

namespace Tests\Feature\Ai;

use App\Jobs\ProcessAiChatRun;
use App\Models\AiChatRun;
use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiConversationBranchService;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Enums\ResponseMode;
use App\Services\Ai\Exceptions\AiEcoUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiEcoModeReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_eco.enabled' => true, 'services.ai_queue_connection' => 'sync']);
        Queue::fake();
    }

    private function user(): User
    {
        return User::factory()->create(['account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function client(): object
    {
        $client = new class implements LlmClientInterface
        {
            public array $requests = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->requests[] = compact('messages', 'tools', 'systemInstruction');

                return new LlmResponse('Penjelasan singkat.');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    public function test_disabled_eco_is_rejected_before_creating_a_conversation_or_job(): void
    {
        config(['ai_eco.enabled' => false]);
        $this->actingAs($this->user())->postJson(route('ai.chat'), ['message' => 'Jelaskan arsitektur.', 'response_mode' => 'eco'])
            ->assertUnprocessable()->assertJsonPath('code', 'eco_unavailable');
        $this->assertDatabaseCount('ai_chat_sessions', 0);
        $this->assertDatabaseCount('ai_chat_messages', 0);
        $this->assertDatabaseCount('ai_chat_runs', 0);
        Queue::assertNotPushed(ProcessAiChatRun::class);
    }

    public function test_missing_schema_rejects_eco_but_default_still_accepts_the_original_payload(): void
    {
        $migration = require database_path('migrations/2026_10_11_000001_add_eco_context_to_ai_chat.php');
        $migration->down();
        try {
            $this->actingAs($this->user())->postJson(route('ai.chat'), ['message' => 'Jelaskan arsitektur.', 'response_mode' => 'eco'])
                ->assertUnprocessable()->assertJsonPath('code', 'eco_unavailable');
            $this->assertDatabaseCount('ai_chat_runs', 0);
            $this->postJson(route('ai.chat'), ['message' => 'Jelaskan arsitektur.'])->assertAccepted();
            $this->assertDatabaseCount('ai_chat_runs', 1);
            Queue::assertPushed(ProcessAiChatRun::class, 1);
        } finally {
            $migration->up();
        }
    }

    public function test_selected_eco_reaches_the_worker_with_eco_prompting_and_tools(): void
    {
        $client = $this->client();
        $response = $this->actingAs($this->user())->postJson(route('ai.chat'), [
            'message' => 'Jelaskan dependency injection.', 'response_mode' => 'eco', 'request_id' => (string) Str::uuid(),
        ])->assertAccepted();
        $run = AiChatRun::findOrFail($response->json('run_id'));
        $this->assertSame('eco', $run->response_mode);
        config(['ai_eco.enabled' => false]);
        $result = app(AiAgentOrchestrator::class)->processRun($run->id);
        $this->assertSame('completed', $result['run']->status);
        $this->assertStringContainsString('[KONTEKS ECO]', $client->requests[0]['systemInstruction']);
        $this->assertCount(4, $client->requests[0]['tools']);
        $this->assertSame('eco', $result['run']->steps->first()->public_metadata['context']['mode']);
    }

    public function test_invalid_frozen_eco_policy_fails_without_a_default_model_call(): void
    {
        $client = $this->client();
        foreach ([null, ['version' => 999], ['version' => 1]] as $policy) {
            $turn = app(AiConversationBranchService::class)->beginTurn($this->user(), 'Jelaskan arsitektur.', responseMode: ResponseMode::Eco);
            $turn['run']->update(['context_policy' => $policy]);
            try {
                app(AiAgentOrchestrator::class)->processRun($turn['run']->id);
            } catch (AiEcoUnavailableException) {
                // The invalid persisted policy must be a terminal, visible failure.
            }
            $run = $turn['run']->fresh();
            $this->assertSame('failed', $run->status);
            $this->assertSame('eco_unavailable', $run->error_code);
            $this->assertSame(0, $run->model_calls);
            $this->actingAs($run->session->user)->getJson(route('ai.runs.show', $run->id))
                ->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('message', 'Mode Eco belum tersedia. Pilih Default atau coba lagi setelah layanan siap.');
        }
        $this->assertSame([], $client->requests);
    }

    public function test_legacy_silent_fallback_cannot_be_replayed_as_an_eco_acceptance(): void
    {
        $user = $this->user();
        $requestId = (string) Str::uuid();
        $turn = app(AiConversationBranchService::class)->beginTurn($user, 'Jelaskan arsitektur.', requestId: $requestId, responseMode: ResponseMode::Eco);
        $turn['run']->update(['response_mode' => 'default', 'context_policy' => null]);
        $this->actingAs($user)->postJson(route('ai.chat'), [
            'message' => 'Jelaskan arsitektur.', 'request_id' => $requestId, 'response_mode' => 'eco',
        ])->assertUnprocessable()->assertJsonPath('code', 'eco_unavailable');
        $this->assertDatabaseCount('ai_chat_runs', 1);
        Queue::assertNotPushed(ProcessAiChatRun::class);
    }

    public function test_eco_edit_is_rejected_without_creating_a_fork_when_disabled(): void
    {
        $user = $this->user();
        $this->client();
        $original = app(AiAgentOrchestrator::class)->handle($user, 'Jelaskan arsitektur.');
        $branchId = $original['session']->fresh()->active_branch_id;
        config(['ai_eco.enabled' => false]);
        $this->actingAs($user)->patchJson(route('ai.messages.edit', $original['user_message']->id), [
            'message' => 'Ubah penjelasan.', 'response_mode' => 'eco',
        ])->assertUnprocessable()->assertJsonPath('code', 'eco_unavailable');
        $this->assertDatabaseCount('ai_chat_branches', 1);
        $this->assertDatabaseCount('ai_chat_runs', 1);
        $this->assertDatabaseCount('ai_chat_messages', 2);
        $this->assertSame($branchId, $original['session']->fresh()->active_branch_id);
    }
}
