<?php

namespace Tests\Feature\Ai;

use App\Jobs\ProcessAiChatRun;
use App\Models\AiChatMessage;
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
use Tests\TestCase;

class AiConversationBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_creates_a_new_branch_without_mutating_or_deleting_the_old_context(): void
    {
        $user = $this->activeUser();
        $this->fakeReplies('Jawaban awal', 'Jawaban lanjutan', 'Jawaban revisi');
        $orchestrator = app(AiAgentOrchestrator::class);
        $first = $orchestrator->handle($user, 'Prompt awal');
        $orchestrator->handle($user, 'Pertanyaan lanjutan', $first['session']->id);
        $originalUserMessage = $first['user_message']->fresh();
        $originalBranchId = $first['branch']->id;

        $response = $this->actingAs($user)->patchJson(
            route('ai.messages.edit', $originalUserMessage),
            ['message' => 'Prompt awal yang direvisi']
        );

        $response->assertAccepted()->assertJsonPath('status', 'accepted');
        $response = $this->getJson(route('ai.runs.show', $response->json('run_id')));
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user_message.revision_index', 2)
            ->assertJsonPath('user_message.revision_count', 2);
        $this->assertDatabaseHas('ai_chat_messages', [
            'id' => $originalUserMessage->id,
            'content' => 'Prompt awal',
            'branch_id' => $originalBranchId,
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'content' => 'Pertanyaan lanjutan',
            'branch_id' => $originalBranchId,
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'content' => 'Prompt awal yang direvisi',
            'branch_id' => $response->json('active_branch_id'),
        ]);
        $this->assertDatabaseCount('ai_chat_branches', 2);
    }

    public function test_switching_versions_changes_the_active_branch_but_preserves_both_branches(): void
    {
        $user = $this->activeUser();
        $this->fakeReplies('Jawaban awal', 'Jawaban revisi');
        $first = app(AiAgentOrchestrator::class)->handle($user, 'Prompt awal');
        $accepted = $this->actingAs($user)->patchJson(
            route('ai.messages.edit', $first['user_message']),
            ['message' => 'Prompt revisi']
        )->assertAccepted();
        $edit = $this->getJson(route('ai.runs.show', $accepted->json('run_id')))->assertOk();

        $this->postJson(route('ai.branches.activate', $first['branch']->id), [])
            ->assertOk()
            ->assertJsonPath('branch_id', $first['branch']->id);

        $this->assertSame($first['branch']->id, $first['session']->fresh()->active_branch_id);
        $this->assertDatabaseHas('ai_chat_branches', ['id' => $edit->json('active_branch_id')]);
    }

    public function test_message_and_branch_endpoints_do_not_expose_another_users_conversation(): void
    {
        $owner = $this->activeUser();
        $intruder = $this->activeUser();
        $this->fakeReplies('Jawaban');
        $result = app(AiAgentOrchestrator::class)->handle($owner, 'Rahasia pemilik');

        $this->actingAs($intruder)
            ->patchJson(route('ai.messages.edit', $result['user_message']), ['message' => 'Ambil alih'])
            ->assertNotFound();
        $this->postJson(route('ai.branches.activate', $result['branch']->id), [])
            ->assertNotFound();
        $this->getJson(route('ai.sessions.messages', ['session' => $result['session']->id, 'before' => $result['assistant_message']->id]))
            ->assertNotFound();
    }

    public function test_running_generation_rejects_an_overlapping_turn(): void
    {
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Sedang berjalan']);
        $branch = $session->branches()->create();
        $session->update(['active_branch_id' => $branch->id]);
        AiChatRun::create([
            'session_id' => $session->id,
            'branch_id' => $branch->id,
            'user_message_id' => 999,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->actingAs($user)->postJson(route('ai.chat'), [
            'session_id' => $session->id,
            'message' => 'Pesan bertabrakan',
        ])->assertConflict();

        $this->assertDatabaseMissing('ai_chat_messages', ['content' => 'Pesan bertabrakan']);
    }

    public function test_expired_run_is_recovered_before_a_new_turn_is_accepted(): void
    {
        Queue::fake();
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Run macet']);
        $branch = $session->branches()->create();
        $session->update(['active_branch_id' => $branch->id]);
        $message = $session->messages()->create([
            'branch_id' => $branch->id,
            'role' => 'user',
            'content' => 'Pesan macet',
            'status' => 'pending',
        ]);
        $branch->update(['head_message_id' => $message->id]);
        $expired = AiChatRun::create([
            'session_id' => $session->id,
            'branch_id' => $branch->id,
            'user_message_id' => $message->id,
            'status' => 'running',
            'started_at' => now()->subMinutes(11),
            'lease_expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->postJson(route('ai.chat'), [
            'session_id' => $session->id,
            'message' => 'Pesan pengganti',
        ])->assertAccepted();

        $this->assertSame('failed', $expired->fresh()->status);
        $this->assertSame('run_lease_expired', $expired->fresh()->error_code);
        $this->assertSame('failed', $message->fresh()->status);
        Queue::assertPushedOn('ai', ProcessAiChatRun::class);
    }

    public function test_stale_run_reaper_recovers_runs_without_user_traffic(): void
    {
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Run tanpa polling']);
        $branch = $session->branches()->create();
        $session->update(['active_branch_id' => $branch->id]);
        $message = $session->messages()->create([
            'branch_id' => $branch->id,
            'role' => 'user',
            'content' => 'Pesan macet',
            'status' => 'pending',
        ]);
        $branch->update(['head_message_id' => $message->id]);
        $run = AiChatRun::create([
            'session_id' => $session->id,
            'branch_id' => $branch->id,
            'user_message_id' => $message->id,
            'status' => 'running',
            'started_at' => now()->subMinutes(11),
            'lease_expires_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, app(AiRunStateManager::class)->expireStale());
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame('run_lease_expired', $message->fresh()->error_code);
        $this->assertNull($branch->fresh()->head_message_id);
    }

    public function test_run_status_is_private_to_the_session_owner(): void
    {
        $owner = $this->activeUser();
        $intruder = $this->activeUser();
        $this->fakeReplies('Jawaban');
        $result = app(AiAgentOrchestrator::class)->handle($owner, 'Rahasia');

        $this->actingAs($intruder)->getJson(route('ai.runs.show', $result['run']->id))->assertNotFound();
    }

    public function test_user_can_cancel_an_owned_run_but_not_another_users_run(): void
    {
        Queue::fake();
        $owner = $this->activeUser();
        $intruder = $this->activeUser();
        $turn = app(AiAgentOrchestrator::class)->enqueue($owner, 'Jawaban panjang');

        $this->actingAs($intruder)
            ->postJson(route('ai.runs.cancel', $turn['run']->id))
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('ai.runs.cancel', $turn['run']->id))
            ->assertOk()
            ->assertJsonPath('run_status', 'cancelled');
        $this->getJson(route('ai.runs.show', $turn['run']->id))
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
        $this->assertDatabaseHas('ai_chat_runs', [
            'id' => $turn['run']->id,
            'status' => 'failed',
            'error_code' => 'user_cancelled',
        ]);
    }

    public function test_cancelled_run_cannot_persist_a_late_provider_response(): void
    {
        Queue::fake();
        $user = $this->activeUser();
        $turn = app(AiAgentOrchestrator::class)->enqueue($user, 'Batalkan respons ini');
        $client = new class implements LlmClientInterface
        {
            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $run = AiChatRun::query()->where('status', 'running')->latest('id')->firstOrFail();
                app(AiRunStateManager::class)->fail($run, 'user_cancelled', false);

                return new LlmResponse(content: 'Jawaban terlambat yang tidak boleh disimpan');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        $this->assertNull(app(AiAgentOrchestrator::class)->processRun($turn['run']->id));
        $this->assertDatabaseMissing('ai_chat_messages', [
            'role' => 'assistant',
            'content' => 'Jawaban terlambat yang tidak boleh disimpan',
        ]);
    }

    public function test_context_assembler_keeps_history_older_than_ten_messages(): void
    {
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Riwayat']);
        for ($index = 0; $index < 12; $index++) {
            $session->messages()->create([
                'role' => $index % 2 === 0 ? 'user' : 'assistant',
                'content' => "Pesan lama {$index}",
                'status' => 'completed',
            ]);
        }
        $client = new class implements LlmClientInterface
        {
            public array $messages = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->messages = $messages;

                return new LlmResponse(content: 'Jawaban baru');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        app(AiAgentOrchestrator::class)->handle($user, 'Pesan ke-13', $session->id);

        $this->assertSame('Pesan lama 0', $client->messages[0]->content);
        $this->assertCount(13, $client->messages);
    }

    public function test_public_process_contains_safe_steps_but_never_raw_reasoning(): void
    {
        $user = $this->activeUser();
        $client = new class implements LlmClientInterface
        {
            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                return new LlmResponse(content: 'Jawaban aman', reasoningContent: 'RAHASIA CHAIN OF THOUGHT');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        $response = $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Tolong bantu']);

        $response->assertAccepted();
        $response = $this->getJson(route('ai.runs.show', $response->json('run_id')));
        $response->assertOk()
            ->assertJsonPath('run.steps.0.kind', 'reasoning_summary')
            ->assertJsonMissingExact(['reasoning_content' => 'RAHASIA CHAIN OF THOUGHT']);
        $this->assertStringNotContainsString('RAHASIA CHAIN OF THOUGHT', $response->getContent());
        $this->assertSame(
            'RAHASIA CHAIN OF THOUGHT',
            AiChatMessage::query()->where('role', 'assistant')->firstOrFail()->reasoning_content
        );
    }

    public function test_workspace_renders_edit_version_and_process_controls_for_the_active_branch(): void
    {
        $user = $this->activeUser();
        $this->fakeReplies('Jawaban awal', 'Jawaban revisi');
        $first = app(AiAgentOrchestrator::class)->handle($user, 'Prompt awal');
        $accepted = $this->actingAs($user)->patchJson(
            route('ai.messages.edit', $first['user_message']),
            ['message' => 'Prompt revisi']
        )->assertAccepted();
        $this->getJson(route('ai.runs.show', $accepted->json('run_id')))->assertOk();

        $this->get(route('ai.workspace', ['session' => $first['session']->id]))
            ->assertOk()
            ->assertSee('data-ai-edit-open', false)
            ->assertSee('data-ai-edit-form', false)
            ->assertSee('2 / 2')
            ->assertSee('data-ai-version-branch="'.$first['branch']->id.'"', false)
            ->assertSee('Proses AI ·')
            ->assertSee('Reasoning ringkas')
            ->assertDontSee('reasoning_content');
    }

    public function test_earlier_messages_endpoint_paginates_only_the_owned_active_lineage(): void
    {
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Panjang']);
        for ($index = 0; $index < 105; $index++) {
            $session->messages()->create([
                'role' => $index % 2 === 0 ? 'user' : 'assistant',
                'content' => "Riwayat {$index}",
                'status' => 'completed',
            ]);
        }

        $page = $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]));
        $page->assertOk()->assertSee('data-ai-load-earlier', false);
        $before = $session->messages()->orderBy('id')->skip(5)->value('id');

        $this->getJson(route('ai.sessions.messages', ['session' => $session->id, 'before' => $before]))
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertSee('Riwayat 0')
            ->assertDontSee('Riwayat 5');
    }

    private function activeUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
            'account_status' => 'active',
        ]);
    }

    private function fakeReplies(string ...$replies): void
    {
        $client = new class($replies) implements LlmClientInterface
        {
            public function __construct(private array $replies) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                return new LlmResponse(content: array_shift($this->replies) ?? 'Jawaban');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
    }
}
