<?php

namespace Tests\Feature\Ai;

use App\Jobs\ProcessAiChatRun;
use App\Models\AiChatRun;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiRunStateManager;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiTurnRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active', 'role' => 'user']);
    }

    public function test_http_only_enqueues_and_separate_worker_completes_the_run(): void
    {
        config(['services.ai_queue_connection' => 'database']);
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;

                return new LlmResponse('Worker completed');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $accepted = $this->actingAs($this->user())->postJson(route('ai.chat'), [
            'message' => 'Halo', 'request_id' => (string) Str::uuid(),
        ])->assertAccepted();
        $this->assertSame(0, $client->calls);
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('ai:work', ['--once' => true])->assertSuccessful();
        $this->assertSame(1, $client->calls);
        $this->assertDatabaseHas('ai_chat_runs', ['id' => $accepted->json('run_id'), 'status' => 'completed']);
    }

    public function test_lost_acceptance_response_can_replay_without_second_job(): void
    {
        Queue::fake();
        $this->actingAs($this->user());
        $payload = ['message' => 'Pesan retry', 'request_id' => (string) Str::uuid()];
        $first = $this->postJson(route('ai.chat'), $payload)->assertAccepted();
        $second = $this->postJson(route('ai.chat'), $payload)->assertAccepted();
        $this->assertSame($first->json('run_id'), $second->json('run_id'));
        $this->assertDatabaseCount('ai_chat_runs', 1);
        Queue::assertPushed(ProcessAiChatRun::class, 1);
    }

    public function test_request_identity_cannot_be_reused_in_another_session(): void
    {
        Queue::fake();
        $user = $this->user();
        $firstSession = AiChatSession::create(['user_id' => $user->id, 'title' => 'A']);
        $secondSession = AiChatSession::create(['user_id' => $user->id, 'title' => 'B']);
        $payload = ['message' => 'Same text', 'request_id' => (string) Str::uuid(), 'session_id' => $firstSession->id];
        $this->actingAs($user)->postJson(route('ai.chat'), $payload)->assertAccepted();
        $payload['session_id'] = $secondSession->id;
        $this->postJson(route('ai.chat'), $payload)->assertConflict();
        $this->assertDatabaseCount('ai_chat_runs', 1);
    }

    public function test_busy_response_exposes_only_the_owned_active_run(): void
    {
        Queue::fake();
        $user = $this->user();
        $turn = app(AiAgentOrchestrator::class)->enqueue($user, 'First');
        $this->actingAs($user)->postJson(route('ai.chat'), [
            'session_id' => $turn['session']->id, 'message' => 'Second', 'request_id' => (string) Str::uuid(),
        ])->assertConflict()->assertJsonPath('code', 'conversation_busy')->assertJsonPath('active_run_id', $turn['run']->id);
        $this->actingAs($this->user())->postJson(route('ai.chat'), [
            'session_id' => $turn['session']->id, 'message' => 'Other user',
        ])->assertUnprocessable();
    }

    public function test_stale_expiry_snapshot_does_not_fail_a_run_whose_lease_was_extended(): void
    {
        Queue::fake();
        $turn = app(AiAgentOrchestrator::class)->enqueue($this->user(), 'First');
        $turn['run']->update(['lease_expires_at' => now()->subSecond()]);
        $stale = $turn['run']->fresh();
        AiChatRun::whereKey($stale->id)->update(['lease_expires_at' => now()->addMinute()]);
        app(AiRunStateManager::class)->expire($stale);
        $this->assertSame('running', $stale->fresh()->status);
    }

    public function test_reusing_a_normal_turn_identity_for_an_edit_is_rejected(): void
    {
        Queue::fake();
        $user = $this->user();
        $id = (string) Str::uuid();
        $turn = app(AiAgentOrchestrator::class)->enqueue($user, 'Same text', requestId: $id);
        $turn['user_message']->update(['status' => 'completed']);
        $this->actingAs($user)->patchJson(route('ai.messages.edit', ['message' => $turn['user_message']->id]), [
            'message' => 'Same text', 'request_id' => $id,
        ])->assertConflict();
        $this->assertDatabaseCount('ai_chat_runs', 1);
        Queue::assertPushed(ProcessAiChatRun::class, 1);
    }
}
