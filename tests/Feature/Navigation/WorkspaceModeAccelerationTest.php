<?php

namespace Tests\Feature\Navigation;

use App\Models\AiChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceModeAccelerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_inactive_mode_is_eligible_for_safe_prerendering(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'account_status' => 'active',
        ]);

        $workspaceResponse = $this->actingAs($user)->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('data-workspace-mode-acceleration', false)
            ->assertSee('selector_matches', false)
            ->assertSee('data-instant-workspace-navigation', false);
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="'.preg_quote(route('workspace.home'), '/').'"[^>]+aria-current="true"/',
            $workspaceResponse->getContent()
        );
        $this->assertSame(1, preg_match_all('/\sdata-instant-workspace-navigation(?=\s)/', $workspaceResponse->getContent()));

        $aiResponse = $this->get(route('ai.workspace', ['new' => 1]))
            ->assertOk()
            ->assertSee('data-instant-workspace-navigation', false);
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="'.preg_quote(route('ai.workspace'), '/').'"[^>]+aria-current="true"/',
            $aiResponse->getContent()
        );
        $this->assertSame(1, preg_match_all('/\sdata-instant-workspace-navigation(?=\s)/', $aiResponse->getContent()));
    }

    public function test_ai_prerender_get_does_not_create_or_repair_persistent_state(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'account_status' => 'active',
        ]);
        $session = AiChatSession::create([
            'user_id' => $user->id,
            'title' => 'Legacy session without a branch',
        ]);

        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk();

        $this->assertDatabaseMissing('user_ai_assistants', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('ai_chat_branches', ['session_id' => $session->id]);
        $this->assertNull($session->fresh()->active_branch_id);
    }

    public function test_guests_do_not_receive_authenticated_navigation_hints(): void
    {
        $this->get('/')
            ->assertDontSee('data-workspace-mode-acceleration', false);
    }
}
