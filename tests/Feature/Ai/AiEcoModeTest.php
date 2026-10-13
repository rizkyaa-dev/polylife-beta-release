<?php

namespace Tests\Feature\Ai;

use App\Jobs\RefreshEcoConversationMemory;
use App\Models\AiChatRun;
use App\Models\AiChatSession;
use App\Models\AiScienceExecution;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiCodingDelegation;
use App\Services\Ai\AiConversationBranchService;
use App\Services\Ai\AiDataRetentionService;
use App\Services\Ai\AiSystemInstructionBuilder;
use App\Services\Ai\AiToolSelectionService;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\ConversationContextAssembler;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmTokenUsage;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Eco\EcoContextAssembler;
use App\Services\Ai\Eco\EcoContextPolicy;
use App\Services\Ai\Eco\EcoContextTools;
use App\Services\Ai\Eco\EcoConversationMemory;
use App\Services\Ai\Eco\EcoToolSelector;
use App\Services\Ai\Enums\ResponseMode;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Exceptions\AiEcoUnavailableException;
use App\Services\Ai\Science\AiScienceDelegation;
use App\Services\Ai\Science\ScienceExecutionBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiEcoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_eco.enabled' => true, 'services.ai_queue_connection' => 'sync']);
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
                $response = array_shift($this->responses) ?? throw new \LogicException('Unexpected additional model call');

                return is_callable($response) ? $response() : $response;
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    private function user(): User
    {
        return User::factory()->create(['account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function conversation(User $user, array $turns): array
    {
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Conversation']);
        $branch = $session->branches()->create();
        $parent = null;
        $messages = collect();
        foreach ($turns as $turn) {
            foreach (['user', 'assistant'] as $role) {
                $message = $session->messages()->create([
                    'branch_id' => $branch->id, 'parent_message_id' => $parent,
                    'revision_group_id' => (string) Str::uuid(), 'role' => $role,
                    'content' => $turn[$role], 'status' => 'completed',
                    'reasoning_content' => $role === 'assistant' ? ($turn['reasoning'] ?? null) : null,
                ]);
                $messages->push($message);
                $parent = $message->id;
            }
        }
        $branch->update(['head_message_id' => $parent]);
        $session->update(['active_branch_id' => $branch->id]);

        return [$session, $branch, $messages];
    }

    private function requestCharacters(array $request): int
    {
        return mb_strlen($request['systemInstruction']) + mb_strlen(json_encode($request['tools'], JSON_THROW_ON_ERROR))
            + array_sum(array_map(fn ($message) => mb_strlen($message->content ?? '') + mb_strlen($message->reasoningContent ?? '')
                + mb_strlen(json_encode($message->toolResult, JSON_THROW_ON_ERROR)), $request['messages']));
    }

    public function test_default_uses_the_exact_legacy_prompt_history_tools_and_fingerprint(): void
    {
        $this->freezeTime();
        $user = $this->user();
        $assistant = UserAiAssistant::create(['user_id' => $user->id, 'assistant_name' => 'Test', 'personality_tone' => 'casual', 'thinking_effort' => 'max']);
        [$session, $branch, $messages] = $this->conversation($user, [['user' => 'Bahas arsitektur.', 'assistant' => 'Mari bahas.', 'reasoning' => 'Original reasoning']]);
        $branch->update(['eco_context_memory' => ['version' => 999]]);
        $prompt = 'Jelaskan dependency injection.';
        $expectedHistory = app(ConversationContextAssembler::class)->assemble($messages, $branch);
        $expectedHistory[] = new LlmMessage('user', $prompt);
        $expectedTools = [...app(AiToolSelectionService::class)->declarations($prompt, $expectedHistory), app(AiCodingDelegation::class)->declaration(), app(AiScienceDelegation::class)->declaration()];
        $expectedInstruction = app(AiSystemInstructionBuilder::class)->build($user, $assistant)
            .app(AiCodingDelegation::class)->instruction().app(AiScienceDelegation::class)->instruction();
        $client = $this->client([new LlmResponse('Dependency injection memasukkan dependensi dari luar.')]);
        $result = app(AiAgentOrchestrator::class)->handle($user, $prompt, $session->id);
        $request = $client->requests[0];

        $this->assertEquals($expectedHistory, $request['messages']);
        $this->assertSame($expectedTools, $request['tools']);
        $this->assertSame($expectedInstruction, $request['systemInstruction']);
        $this->assertSame(ThinkingEffort::Max, $request['options']->thinkingEffort);
        $this->assertSame(hash('sha256', json_encode([$prompt, $session->id, null], JSON_THROW_ON_ERROR)), $result['run']->request_fingerprint);
        $this->assertNull($result['run']->context_policy);
        $this->assertSame(['version' => 999], $branch->fresh()->eco_context_memory);
    }

    public function test_long_history_eco_request_is_smaller_without_changing_effort_or_default_digest(): void
    {
        $user = $this->user();
        UserAiAssistant::create(['user_id' => $user->id, 'assistant_name' => 'Test', 'personality_tone' => 'casual', 'thinking_effort' => 'high']);
        [$session, $branch, $messages] = $this->conversation($user, array_fill(0, 14, [
            'user' => 'Diskusi topik lama '.str_repeat('unrelated ', 40),
            'assistant' => str_repeat('older explanation ', 160), 'reasoning' => str_repeat('older reasoning ', 35),
        ]));
        $client = $this->client([new LlmResponse('Penjelasan.'), new LlmResponse('Penjelasan.')]);
        app(AiAgentOrchestrator::class)->handle($user, 'Jelaskan dependency injection.', $session->id);
        $digest = $branch->fresh()->context_digest;
        $ecoBranch = $session->branches()->create(['head_message_id' => $messages->last()->id, 'forked_from_message_id' => $messages->last()->id]);
        $session->update(['active_branch_id' => $ecoBranch->id]);
        $eco = app(AiAgentOrchestrator::class)->handle($user, 'Jelaskan dependency injection.', $session->id, ResponseMode::Eco);

        $this->assertLessThan($this->requestCharacters($client->requests[0]) * 0.5, $this->requestCharacters($client->requests[1]));
        $this->assertCount(4, $client->requests[1]['tools']);
        $this->assertSame($client->requests[0]['options']->thinkingEffort, $client->requests[1]['options']->thinkingEffort);
        $this->assertSame($digest, $branch->fresh()->context_digest);
        $this->assertNull($ecoBranch->fresh()->context_digest);
        $this->assertTrue($eco['run']->steps->first()->public_metadata['context']['source_messages'] > 20);
        $this->assertCount(2, $client->requests); // Memory refresh has no inference.
    }

    public function test_mode_validation_and_foreign_session_reject_before_creating_a_run(): void
    {
        Queue::fake();
        $user = $this->user();
        $foreign = AiChatSession::create(['user_id' => $this->user()->id, 'title' => 'Private']);
        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Hi', 'response_mode' => 'cheap'])
            ->assertUnprocessable()->assertJsonValidationErrors('response_mode');
        $this->postJson(route('ai.chat'), ['message' => 'Hi', 'response_mode' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('response_mode');
        $this->postJson(route('ai.chat'), ['message' => 'Hi', 'response_mode' => 'eco', 'session_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('session_id');
        $this->assertDatabaseCount('ai_chat_runs', 0);
    }

    public function test_eco_send_is_idempotent_and_changing_mode_conflicts(): void
    {
        Queue::fake();
        $payload = ['message' => 'Hi', 'request_id' => (string) Str::uuid(), 'response_mode' => 'eco'];
        $first = $this->actingAs($this->user())->postJson(route('ai.chat'), $payload)->assertAccepted();
        $this->postJson(route('ai.chat'), $payload)->assertAccepted()->assertJsonPath('run_id', $first->json('run_id'));
        $this->postJson(route('ai.chat'), [...$payload, 'response_mode' => 'default'])->assertConflict();
        $run = AiChatRun::firstOrFail();
        $this->assertSame('eco', $run->response_mode);
        $this->assertSame(EcoContextPolicy::VERSION, $run->context_policy['version']);
        $this->assertDatabaseCount('ai_chat_runs', 1);
    }

    public function test_eco_edit_is_validated_and_excludes_discarded_sibling_evidence(): void
    {
        $user = $this->user();
        [$session, , $messages] = $this->conversation($user, [['user' => 'First prompt', 'assistant' => 'SIBLING_SECRET']]);
        $this->actingAs($user)->patchJson(route('ai.messages.edit', $messages->first()), ['message' => 'Revised', 'response_mode' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors('response_mode');
        $client = $this->client([new LlmResponse('Revised reply')]);
        $this->patchJson(route('ai.messages.edit', $messages->first()), ['message' => 'Revised', 'response_mode' => 'eco'])->assertAccepted();
        $this->assertStringNotContainsString('SIBLING_SECRET', json_encode($client->requests));
        $this->assertSame('eco', AiChatRun::firstOrFail()->response_mode);
        $this->assertDatabaseCount('ai_chat_branches', 2);
    }

    public function test_queued_eco_policy_remains_frozen_when_configuration_changes(): void
    {
        Queue::fake();
        config(['ai_eco.context_token_budget' => 4200]);
        $turn = app(AiConversationBranchService::class)->beginTurn($this->user(), 'Jelaskan arsitektur.', responseMode: ResponseMode::Eco);
        config(['ai_eco.enabled' => false, 'ai_eco.context_token_budget' => 18000]);
        $client = $this->client([new LlmResponse('Penjelasan.')]);
        $result = app(AiAgentOrchestrator::class)->processRun($turn['run']->id);

        $this->assertSame(4200, $result['run']->steps->first()->public_metadata['context']['target_input_tokens']);
        $this->assertCount(4, $client->requests[0]['tools']);
        $this->assertNull($result['branch']->fresh()->context_digest);
    }

    public function test_disabled_eco_rejects_admission_without_creating_a_default_run(): void
    {
        Queue::fake();
        config(['ai_eco.enabled' => false]);
        $client = $this->client([]);
        try {
            app(AiConversationBranchService::class)->beginTurn($this->user(), 'Hi', responseMode: ResponseMode::Eco);
            $this->fail('Unavailable Eco must not admit a Default turn.');
        } catch (AiEcoUnavailableException $exception) {
            $this->assertSame('disabled', $exception->reason);
        }

        $this->assertDatabaseCount('ai_chat_runs', 0);
        $this->assertDatabaseCount('ai_chat_messages', 0);
        $this->assertSame([], $client->requests);
        Queue::assertNotPushed(RefreshEcoConversationMemory::class);
    }

    public function test_discovery_expands_tools_and_all_model_calls_are_accounted(): void
    {
        $usage = new LlmTokenUsage(100, 10);
        $client = $this->client([
            new LlmResponse(null, [new LlmToolCall('find', EcoToolSelector::DISCOVER, ['domains' => ['notes']])], usage: $usage),
            new LlmResponse(null, [new LlmToolCall('read', 'search_catatan', ['query' => 'arsitektur'])], usage: $usage),
            new LlmResponse('Belum ada hasil.', usage: $usage),
        ]);
        $result = app(AiAgentOrchestrator::class)->handle($this->user(), 'Temukan informasi arsitektur.', responseMode: ResponseMode::Eco);

        $this->assertNotContains('search_catatan', array_column($client->requests[0]['tools'], 'name'));
        $this->assertContains('search_catatan', array_column($client->requests[1]['tools'], 'name'));
        $this->assertCount(count(array_unique(array_column($client->requests[1]['tools'], 'name'))), $client->requests[1]['tools']);
        $this->assertSame(3, $result['run']->model_calls);
        $this->assertSame(300, $result['run']->prompt_tokens);
        $this->assertSame(30, $result['run']->completion_tokens);
        $this->assertSame('complete', $result['run']->tokenUsageStatus());
    }

    public function test_recall_returns_exact_old_text_and_rejects_foreign_and_future_messages(): void
    {
        $user = $this->user();
        [$session, , $messages] = $this->conversation($user, [
            ['user' => 'Pilihan awal: HANYA PostgreSQL, nilai 0.002 kg.', 'assistant' => 'Baik.'],
            ['user' => 'Future message', 'assistant' => 'FUTURE_SECRET'],
        ]);
        [, , $foreign] = $this->conversation($this->user(), [['user' => 'FOREIGN_SECRET', 'assistant' => 'Private']]);
        $tools = app(EcoContextTools::class);
        $head = $messages[1]->id;
        $result = $tools->recall($user, $session, $head, ['message_ids' => [$messages[0]->id]], EcoContextPolicy::configured());
        $this->assertSame($messages[0]->content, $result['messages'][0]['content']);
        foreach ([$foreign[0]->id, $messages[3]->id] as $id) {
            try {
                $tools->recall($user, $session, $head, ['message_ids' => [$id]], EcoContextPolicy::configured());
                $this->fail('Unauthorized evidence was returned');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('message_ids', $exception->errors());
            }
        }
    }

    public function test_context_preparation_gets_one_bounded_round_without_reducing_workspace_capacity(): void
    {
        $responses = [new LlmResponse(null, [new LlmToolCall('discover', EcoToolSelector::DISCOVER, ['domains' => ['notes']])])];
        foreach (['first', 'second', 'third', 'fourth'] as $query) {
            $responses[] = new LlmResponse(null, [new LlmToolCall($query, 'search_catatan', ['query' => $query])]);
        }
        $responses[] = new LlmResponse('Collected evidence.');
        $client = $this->client($responses);
        $result = app(AiAgentOrchestrator::class)->handle($this->user(), 'Collect information.', responseMode: ResponseMode::Eco);
        $this->assertSame('Collected evidence.', $result['reply']);
        $this->assertCount(6, $client->requests);
        $this->assertNotEmpty($client->requests[4]['tools']);
        $this->assertSame([], $client->requests[5]['tools']);
    }

    public function test_recall_tool_uses_original_evidence_in_the_next_model_round(): void
    {
        $user = $this->user();
        [$session, , $messages] = $this->conversation($user, array_merge([
            ['user' => 'Kontrak lama: timeout tepat 37 detik.', 'assistant' => 'Baik.'],
        ], array_fill(0, 5, ['user' => 'Obrolan lain', 'assistant' => 'Jawaban lain'])));
        $client = $this->client([
            new LlmResponse(null, [new LlmToolCall('recall', EcoToolSelector::RECALL, ['message_ids' => [$messages[0]->id]])]),
            new LlmResponse('Timeout 37 detik.'),
        ]);
        app(AiAgentOrchestrator::class)->handle($user, 'Apa timeout yang dulu?', $session->id, ResponseMode::Eco);
        $tool = collect($client->requests[1]['messages'])->firstWhere('role', 'tool');

        $this->assertSame($messages[0]->content, $tool->toolResult['result']['messages'][0]['content']);
        $this->assertSame('recall', $tool->toolResult['call_id']);
    }

    public function test_coder_receives_exact_source_while_main_only_receives_artifact_reference(): void
    {
        $user = $this->user();
        $source = "```html\n<!doctype html><html><body>ORIGINAL_ARTIFACT".str_repeat('<p>Original source</p>', 1000)."</body></html>\n```";
        [$session, , $messages] = $this->conversation($user, [['user' => 'Buat halaman HTML gelap.', 'assistant' => $source, 'reasoning' => 'Original artifact reasoning']]);
        $client = $this->client([
            new LlmResponse(null, [new LlmToolCall('code', 'delegate_code_generation', [
                'language' => 'html', 'runtime' => 'browser', 'files' => ['index.html'],
                'requirements' => ['Perbaiki tombol'], 'acceptance_criteria' => ['HTML standalone'],
                'source_message_id' => $messages[1]->id,
            ])]),
            new LlmResponse("```html\n<!doctype html><html><body>Updated</body></html>\n```"),
        ]);
        app(AiAgentOrchestrator::class)->handle($user, 'Ubah tombol tadi.', $session->id, ResponseMode::Eco);
        $main = json_encode($client->requests[0]['messages']);
        $coder = $client->requests[1]['messages'][0]->content;
        $envelope = json_decode(trim(str_replace(['<delegated_coding_request>', '</delegated_coding_request>'], '', $coder)), true, flags: JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('ORIGINAL_ARTIFACT', $main);
        $this->assertStringContainsString('artifact_reference', $main);
        $this->assertSame($source, $envelope['source_artifact']);
        $this->assertSame([], $client->requests[1]['tools']);
        $this->assertCount(2, $client->requests);
    }

    public function test_recent_reasoning_and_user_constraints_are_never_cut_to_fit_the_soft_target(): void
    {
        $user = $this->user();
        $reasoning = str_repeat('Preserve provider reasoning. ', 1000);
        $constraint = str_repeat('scientific input ', 300).'\nJangan ubah 0.002 kg atau 273.15 K.';
        [$session, $branch, $messages] = $this->conversation($user, [['user' => $constraint, 'assistant' => 'Jawaban.', 'reasoning' => $reasoning]]);
        $result = app(EcoContextAssembler::class)->assemble($messages, $branch, 'Lanjutkan.', 'Rules', [], new EcoContextPolicy(2000, 2, 200, 240));
        $this->assertSame($constraint, collect($result['history'])->firstWhere('role', 'user')->content);
        $this->assertSame($reasoning, collect($result['history'])->firstWhere('role', 'assistant')->reasoningContent);
        $this->assertTrue($result['metadata']['target_exceeded']);
    }

    public function test_old_constraint_in_the_middle_of_a_long_message_survives_excerpting(): void
    {
        $user = $this->user();
        $critical = 'Jangan ubah API PostgreSQL: port tetap 5433.';
        [$session, $branch, $messages] = $this->conversation($user, array_merge([
            ['user' => str_repeat('context ', 180)."\n{$critical}\n".str_repeat('context ', 180), 'assistant' => 'Baik.'],
        ], array_fill(0, 5, ['user' => 'Topik lainnya', 'assistant' => 'Penjelasan '.str_repeat('older ', 400)])));
        $result = app(EcoContextAssembler::class)->assemble($messages, $branch, 'Lanjutkan API PostgreSQL.', str_repeat('fixed ', 1200), [], new EcoContextPolicy(2000, 1, 600, 240));
        $text = implode(' ', array_map(fn ($message) => $message->content, $result['history']));
        $this->assertStringContainsString($critical, $text);
    }

    public function test_memory_is_encrypted_incremental_and_separate_from_default_digest(): void
    {
        $user = $this->user();
        $client = $this->client([new LlmResponse('First answer'), new LlmResponse('Second answer')]);
        $first = app(AiAgentOrchestrator::class)->handle($user, 'PRIVATE_USER_DETAIL', responseMode: ResponseMode::Eco);
        $branch = $first['branch']->fresh();
        $this->assertStringNotContainsString('PRIVATE_USER_DETAIL', $branch->getRawOriginal('eco_context_memory'));
        $this->assertSame($first['assistant_message']->id, $branch->eco_context_memory['through_message_id']);
        $this->assertNull($branch->context_digest);
        $second = app(AiAgentOrchestrator::class)->handle($user, 'Lanjutkan.', $first['session']->id, ResponseMode::Eco);
        $this->assertTrue($second['run']->steps->first()->public_metadata['context']['memory_cache_hit']);
        $this->assertSame($second['assistant_message']->id, $branch->fresh()->eco_context_memory['through_message_id']);
        $this->assertCount(2, $client->requests);
    }

    public function test_stale_memory_job_cannot_replace_a_newer_snapshot(): void
    {
        $user = $this->user();
        $this->client([new LlmResponse('First answer'), new LlmResponse('Second answer')]);
        $first = app(AiAgentOrchestrator::class)->handle($user, 'First', responseMode: ResponseMode::Eco);
        $second = app(AiAgentOrchestrator::class)->handle($user, 'Second', $first['session']->id, ResponseMode::Eco);
        $snapshot = $second['branch']->fresh()->getRawOriginal('eco_context_memory');
        app(EcoConversationMemory::class)->refresh($first['run']->id);
        $this->assertSame($snapshot, $second['branch']->fresh()->getRawOriginal('eco_context_memory'));
    }

    public function test_source_correction_and_malformed_memory_rebuild_from_authorized_sources(): void
    {
        $user = $this->user();
        $this->client([new LlmResponse('Answer')]);
        $first = app(AiAgentOrchestrator::class)->handle($user, 'Old value', responseMode: ResponseMode::Eco);
        $first['user_message']->update(['content' => 'Corrected value']);
        $lineage = app(AiConversationBranchService::class)->recentLineage($first['session'], $first['assistant_message']->id);
        $memory = app(EcoConversationMemory::class)->read($lineage, $first['branch']->fresh());
        $this->assertFalse($memory['cache_hit']);
        $this->assertSame('Corrected value', $memory['entries'][0]['quote']);
        $first['branch']->update(['eco_context_memory' => ['version' => 1, 'through_message_id' => [], 'through_hash' => []]]);
        $this->assertFalse(app(EcoConversationMemory::class)->read($lineage, $first['branch']->fresh())['cache_hit']);
    }

    public function test_retention_and_session_deletion_remove_derived_memory(): void
    {
        $user = $this->user();
        $this->client([new LlmResponse('Answer')]);
        $turn = app(AiAgentOrchestrator::class)->handle($user, 'Private message', responseMode: ResponseMode::Eco);
        $turn['branch']->update(['eco_memory_refreshed_at' => now()->subDays(31)]);
        app(AiDataRetentionService::class)->prune();
        $this->assertNull($turn['branch']->fresh()->eco_context_memory);
        $turn['session']->delete();
        $this->assertDatabaseMissing('ai_chat_branches', ['id' => $turn['branch']->id]);
    }

    public function test_coder_cannot_resolve_an_artifact_outside_the_original_ancestor_path(): void
    {
        $user = $this->user();
        [$session, $branch, $messages] = $this->conversation($user, [['user' => 'Awal.', 'assistant' => 'Jawaban.']]);
        $sibling = $session->branches()->create(['head_message_id' => $messages->last()->id]);
        $artifact = $session->messages()->create([
            'branch_id' => $sibling->id, 'parent_message_id' => $messages->last()->id,
            'revision_group_id' => (string) Str::uuid(), 'role' => 'assistant',
            'content' => "```html\nSIBLING_ARTIFACT_SECRET\n```", 'status' => 'completed',
        ]);
        $client = $this->client([
            new LlmResponse(null, [new LlmToolCall('code', AiCodingDelegation::TOOL_NAME, [
                'language' => 'html', 'runtime' => 'browser', 'files' => ['index.html'],
                'requirements' => ['Perbaiki halaman'], 'acceptance_criteria' => ['HTML standalone'],
                'source_message_id' => $artifact->id,
            ])]),
            new LlmResponse('Artefak itu tidak tersedia. Mohon pilih sumber pada percakapan ini.'),
        ]);

        app(AiAgentOrchestrator::class)->handle($user, 'Revisi halaman.', $session->id, ResponseMode::Eco);
        $this->assertCount(2, $client->requests);
        $tool = collect($client->requests[1]['messages'])->last();
        $this->assertSame('invalid_arguments', $tool->toolResult['result']['status']);
        $this->assertStringNotContainsString('SIBLING_ARTIFACT_SECRET', json_encode($client->requests));
    }

    public function test_science_resume_keeps_older_recalled_artifact_available_to_coder(): void
    {
        Queue::fake();
        config(['services.ai_science_browser_enabled' => true, 'services.ai_queue_connection' => 'database']);
        $user = $this->user();
        $source = "```html\n<!doctype html><html><body>OLD_EXACT_ARTIFACT</body></html>\n```";
        [$session, , $messages] = $this->conversation($user, [
            ['user' => 'Buat halaman HTML.', 'assistant' => $source],
            ...array_fill(0, 121, ['user' => 'Diskusi lain.', 'assistant' => 'Catatan lain.']),
        ]);
        $sourceId = $messages[1]->id;
        $this->assertFalse(app(AiConversationBranchService::class)->recentLineage($session, $messages->last()->id)->contains('id', $sourceId));
        $client = $this->client([
            new LlmResponse(null, [new LlmToolCall('recall', EcoToolSelector::RECALL, ['message_ids' => [$sourceId]])]),
            new LlmResponse(null, [new LlmToolCall('science', AiScienceDelegation::TOOL_NAME, ['problem' => 'Solve equations.'])]),
            new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Unknown order x,y.', 'assumptions' => [], 'units' => ['dimensionless'],
                'solver' => 'linear_system', 'inputs' => ['matrix' => [[1, 2], [3, 4]], 'rhs' => [5, 11]]])),
            fn () => new LlmResponse(null, [new LlmToolCall('code', AiCodingDelegation::TOOL_NAME, [
                'language' => 'html', 'runtime' => 'browser', 'files' => ['index.html'],
                'requirements' => ['Tampilkan solusi pada halaman lama'], 'acceptance_criteria' => ['HTML standalone'],
                'source_message_id' => $sourceId, 'science_step_id' => AiScienceExecution::firstOrFail()->step_id,
            ])]),
            new LlmResponse("```html\n<!doctype html><html><body>x=1, y=2</body></html>\n```"),
        ]);
        $orchestrator = app(AiAgentOrchestrator::class);
        $turn = $orchestrator->enqueue($user, 'Revisi halaman lama dengan solusi sistem persamaan.', $session->id, scienceClient: true, responseMode: ResponseMode::Eco);
        $this->assertSame('awaiting_science', $orchestrator->processRun($turn['run']->id)['phase']);
        $ticket = AiScienceExecution::firstOrFail();
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'result' => ['status' => 'computed', 'solution' => [1, 2]]]);
        $broker->execute($ticket->id);

        $result = $orchestrator->processRun($turn['run']->id);
        $this->assertSame('completed', $result['run']->status);
        $this->assertCount(5, $client->requests);
        $coder = $client->requests[4]['messages'][0]->content;
        $envelope = json_decode(trim(str_replace(['<delegated_coding_request>', '</delegated_coding_request>'], '', $coder)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($source, $envelope['source_artifact']);
        $this->assertNotNull($envelope['science_contract']);
    }

    public function test_science_resume_uses_checkpoint_and_does_not_repeat_planning(): void
    {
        Queue::fake();
        config(['services.ai_science_browser_enabled' => true, 'services.ai_queue_connection' => 'database']);
        $user = $this->user();
        $client = $this->client([
            new LlmResponse(null, [new LlmToolCall('science', 'delegate_science_problem', ['problem' => 'Solve equations.'])]),
            new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Unknown order x,y.', 'assumptions' => [], 'units' => ['dimensionless'],
                'solver' => 'linear_system', 'inputs' => ['matrix' => [[1, 2], [3, 4]], 'rhs' => [5, 11]]])),
            new LlmResponse('x=1, y=2. Physical formulation unverified.'),
        ]);
        $turn = app(AiAgentOrchestrator::class)->enqueue($user, 'Solve x+2y=5 and 3x+4y=11.', scienceClient: true, responseMode: ResponseMode::Eco);
        $orchestrator = app(AiAgentOrchestrator::class);
        $this->assertSame('awaiting_science', $orchestrator->processRun($turn['run']->id)['phase']);
        $ticket = AiScienceExecution::firstOrFail();
        $checkpointHistory = $ticket->private_payload['checkpoint']['history'];
        $checkpointInstruction = $ticket->private_payload['checkpoint']['system_instruction'];
        $this->assertTrue($ticket->private_payload['checkpoint']['eco_science_evidence_loaded']);
        $this->assertSame(1, substr_count($checkpointInstruction, '[SCIENCE EVIDENCE ECO]'));
        config(['ai_eco.enabled' => false, 'ai_eco.context_token_budget' => 24000]);
        $broker = app(ScienceExecutionBroker::class);
        $claim = $broker->claim($ticket, (string) Str::uuid());
        $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'result' => ['status' => 'computed', 'solution' => [999, 999]]]);
        $broker->execute($ticket->id);
        $result = $orchestrator->processRun($turn['run']->id);
        $this->assertSame('completed', $result['run']->status);
        $this->assertCount(3, $client->requests);
        $this->assertSame($checkpointInstruction, $client->requests[2]['systemInstruction']);
        $this->assertSame($checkpointHistory, array_map(fn ($message) => $message->toArray(), array_slice($client->requests[2]['messages'], 0, count($checkpointHistory))));
        $tool = collect($client->requests[2]['messages'])->last();
        $this->assertSame([1, 2], $tool->toolResult['result']['result']['solution']);
        $this->assertNull($result['branch']->fresh()->context_digest);
    }
}
