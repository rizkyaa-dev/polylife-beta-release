<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\AiConversationBranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiLegacyHistoryOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function history(): array
    {
        $session = AiChatSession::create(['user_id' => User::factory()->create()->id, 'title' => 'Legacy']);
        $ids = [];
        $timestamp = now()->subMinutes(10);
        for ($i = 0; $i < 6; $i++) {
            $message = $session->messages()->make(['role' => 'user', 'status' => 'completed', 'content' => 'Message '.$i]);
            // created_at is intentionally not mass assignable on the production model.
            $message->created_at = $timestamp->copy()->addMinutes($i);
            $message->save();
            $ids[] = $message->id;
        }

        return [$session, $ids];
    }

    public function test_recent_legacy_window_overrides_relation_order_before_applying_limit(): void
    {
        [$session, $ids] = $this->history();
        $messages = app(AiConversationBranchService::class)->recentActiveLineage($session, 2);
        $this->assertSame(array_slice($ids, -2), $messages->pluck('id')->all());
    }

    public function test_legacy_cursor_page_contains_nearest_previous_messages_not_oldest_messages(): void
    {
        [$session, $ids] = $this->history();
        $page = app(AiConversationBranchService::class)->completedPageBefore($session, $ids[5], 2);
        $this->assertSame([$ids[3], $ids[4]], $page['messages']->pluck('id')->all());
        $this->assertTrue($page['has_more']);
    }
}
