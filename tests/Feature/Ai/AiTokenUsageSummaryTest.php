<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatRun;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiChatResponseFactory;
use App\Services\Ai\AiTokenUsageSummary;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmTokenUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiTokenUsageSummaryTest extends TestCase
{
    use RefreshDatabase;

    public static function measurements(): array
    {
        return ['positive usage' => [100, 10], 'measured zero' => [0, 0]];
    }

    #[DataProvider('measurements')]
    public function test_legacy_answers_keep_a_session_partial_after_a_measured_turn(int $prompt, int $completion): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Legacy']);
        $session->messages()->createMany([
            ['role' => 'user', 'status' => 'completed', 'content' => 'Old question'],
            ['role' => 'assistant', 'status' => 'completed', 'content' => 'Unmeasured answer'],
        ]);
        $run = $this->measuredTurn($user, $session->id, new LlmTokenUsage($prompt, $completion));

        $expected = ['prompt' => $prompt, 'completion' => $completion, 'total' => $prompt + $completion, 'status' => 'partial'];
        $this->assertSame('complete', $run->tokenUsageStatus());
        $this->assertSame($expected, AiTokenUsageSummary::session($session->id));
        $this->assertSame($expected, app(AiChatResponseFactory::class)->completed($run)['session_tokens']);
        $this->assertSame(1, $session->runs()->count());
        $this->actingAs($user)->getJson(route('ai.runs.show', ['run' => $run->id]))
            ->assertOk()->assertJsonPath('session_tokens.status', 'partial')
            ->assertJsonPath('session_tokens.total', $prompt + $completion);
        $this->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()->assertViewHas('sessionTokens', $expected);
    }

    public function test_an_entirely_legacy_session_stays_unknown_without_writing_synthetic_runs(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Legacy']);
        $session->messages()->create(['role' => 'assistant', 'status' => 'completed', 'content' => 'Old answer']);
        $expected = ['prompt' => 0, 'completion' => 0, 'total' => 0, 'status' => 'unknown'];

        $this->assertSame($expected, AiTokenUsageSummary::session($session->id));
        $this->assertSame($expected, AiTokenUsageSummary::session($session->id));
        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()->assertViewHas('sessionTokens', $expected);
        $this->assertSame(0, $session->runs()->count());
        $this->assertSame(0, $session->branches()->count());
        $this->assertNull($session->fresh()->active_branch_id);
    }

    public function test_unanswered_messages_and_other_sessions_do_not_invalidate_complete_usage(): void
    {
        $user = User::factory()->create();
        $run = $this->measuredTurn($user);
        $run->session->messages()->createMany([
            ['role' => 'user', 'status' => 'completed', 'content' => 'Unanswered question'],
            ['role' => 'assistant', 'status' => 'pending', 'content' => 'Pending'],
            ['role' => 'assistant', 'status' => 'failed', 'content' => 'Failed'],
        ]);
        foreach ([$user, User::factory()->create()] as $owner) {
            $other = AiChatSession::create(['user_id' => $owner->id, 'title' => 'Other legacy session']);
            $other->messages()->create(['role' => 'assistant', 'status' => 'completed', 'content' => 'Unmeasured']);
        }

        $this->assertSame(['prompt' => 100, 'completion' => 10, 'total' => 110, 'status' => 'complete'],
            AiTokenUsageSummary::session($run->session_id));
        $this->assertSame(['prompt' => 0, 'completion' => 0, 'total' => 0, 'status' => 'unknown'],
            AiTokenUsageSummary::session(null));
    }

    public function test_a_run_from_another_session_cannot_cover_an_untracked_answer(): void
    {
        $user = User::factory()->create();
        $run = $this->measuredTurn($user);
        $untracked = $run->session->messages()->create(['role' => 'assistant', 'status' => 'completed', 'content' => 'Unmeasured']);
        $otherRun = $this->measuredTurn($user);
        $otherRun->update(['assistant_message_id' => $untracked->id]);

        $this->assertSame('partial', AiTokenUsageSummary::session($run->session_id)['status']);
        $this->assertSame(110, AiTokenUsageSummary::session($run->session_id)['total']);
    }

    private function measuredTurn(User $user, ?int $sessionId = null, ?LlmTokenUsage $usage = null): AiChatRun
    {
        $this->app->instance(LlmClientInterface::class, new class($usage ?? new LlmTokenUsage(100, 10)) implements LlmClientInterface
        {
            public function __construct(private readonly LlmTokenUsage $usage) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                return new LlmResponse('Measured answer', usage: $this->usage);
            }
        });

        return app(AiAgentOrchestrator::class)->handle($user, 'Measure this turn', $sessionId)['run'];
    }
}
