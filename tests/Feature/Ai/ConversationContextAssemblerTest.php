<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatBranch;
use App\Models\AiChatMessage;
use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\ConversationContextAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationContextAssemblerTest extends TestCase
{
    use RefreshDatabase;

    public static function budgets(): array
    {
        return [[2000, 5800, 10000], [24000, 70000, 10000], [2000, 5800, 500]];
    }

    #[DataProvider('budgets')]
    public function test_all_context_including_tool_facts_stays_within_budget(int $tokens, int $messageSize, int $factSize): void
    {
        config(['services.ai_context_token_budget' => $tokens]);
        [$branch, $messages] = $this->conversation([$messageSize]);
        $this->fact($messages->last(), str_repeat('f', $factSize));
        $context = app(ConversationContextAssembler::class)->assemble($messages, $branch);
        $this->assertLessThanOrEqual($tokens * 3, $this->length($context));
        $this->assertSame('assistant', $context[array_key_last($context)]->role);
        $this->assertStringStartsWith('m', $context[array_key_last($context)]->content);
        foreach ($context as $item) {
            if (str_starts_with($item->content, '[ARSIP HASIL TOOL]')) {
                $facts = json_decode(substr($item->content, strpos($item->content, '[{')), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(str_repeat('f', $factSize), $facts[0]['result']['content']);
            }
        }
    }

    public function test_cached_digest_and_unicode_reasoning_cannot_overflow_a_reduced_budget(): void
    {
        config(['services.ai_context_token_budget' => 24000]);
        [$branch, $messages] = $this->conversation(array_fill(0, 90, 1500));
        $assembler = app(ConversationContextAssembler::class);
        $assembler->assemble($messages, $branch);
        config(['services.ai_context_token_budget' => 2000]);
        $branch->update(['context_digest' => str_repeat('arsip ', 5000), 'digest_through_message_id' => $messages[$messages->count() - 3]->id]);
        $messages->last()->update(['reasoning_content' => str_repeat('漢', 1000)]);
        $context = $assembler->assemble($messages, $branch);
        $this->assertLessThanOrEqual(6000, $this->length($context));
        $this->assertStringContainsString('m', $context[array_key_last($context)]->content);
    }

    public function test_small_history_and_tool_facts_are_retained_without_compaction(): void
    {
        [$branch, $messages] = $this->conversation([100]);
        $this->fact($messages->last(), 'historical evidence');
        $context = app(ConversationContextAssembler::class)->assemble($messages, $branch);
        $this->assertCount(2, $context);
        $this->assertSame(str_repeat('m', 100), $context[1]->content);
        $this->assertStringContainsString('historical evidence', $context[0]->content);
        $this->assertNull($branch->fresh()->context_digest);
    }

    private function conversation(array $sizes): array
    {
        $session = AiChatSession::create(['user_id' => User::factory()->create()->id, 'title' => 'Context']);
        $branch = AiChatBranch::create(['session_id' => $session->id]);
        $messages = collect($sizes)->map(fn ($size) => AiChatMessage::create([
            'session_id' => $session->id, 'branch_id' => $branch->id, 'role' => 'assistant',
            'status' => 'completed', 'content' => str_repeat('m', $size),
        ]));

        return [$branch, $messages];
    }

    private function fact(AiChatMessage $message, string $content): void
    {
        $run = AiChatRun::create([
            'session_id' => $message->session_id, 'branch_id' => $message->branch_id,
            'user_message_id' => $message->id, 'assistant_message_id' => $message->id,
            'status' => 'completed', 'started_at' => now(),
        ]);
        AiChatRunStep::create([
            'run_id' => $run->id, 'sequence' => 1, 'kind' => 'tool_call', 'tool_name' => 'get_catatan',
            'status' => 'completed', 'label' => 'Read', 'private_payload' => ['content' => $content],
        ]);
    }

    private function length(array $context): int
    {
        return array_sum(array_map(fn ($message) => mb_strlen($message->content ?? '') + mb_strlen($message->reasoningContent ?? ''), $context));
    }
}
