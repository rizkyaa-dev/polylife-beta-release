<?php

namespace Tests\Feature\Ai;

use App\Models\AiActionProposal;
use App\Models\AiChatRun;
use App\Models\AiChatSession;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\Enums\ThinkingEffort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiWorkspaceViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_mode_uses_chat_navigation_and_workspace_mode_keeps_workspace_navigation(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $this->actingAs($user)->get(route('ai.workspace', ['new' => 1]))
            ->assertOk()
            ->assertSee('aria-label="Mode AI"', false)
            ->assertSee('aria-label="Percakapan AI"', false)
            ->assertDontSee('aria-label="Menu workspace"', false)
            ->assertSee('data-ai-input', false);

        $this->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('aria-label="Mode AI"', false)
            ->assertSee('aria-label="Menu workspace"', false)
            ->assertDontSee('aria-label="Percakapan AI"', false);
    }

    public function test_deepseek_workspace_shows_accessible_thinking_control_with_saved_effort(): void
    {
        config(['services.ai_provider' => 'deepseek']);
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        UserAiAssistant::create([
            'user_id' => $user->id,
            'assistant_name' => 'PolyBot',
            'personality_tone' => 'friendly_peer',
            'thinking_effort' => ThinkingEffort::Low,
        ]);

        $response = $this->actingAs($user)->get(route('ai.workspace', ['new' => 1]));

        $response->assertOk()
            ->assertSee('data-ai-thinking', false)
            ->assertSee('Atur tingkat thinking, saat ini Low')
            ->assertSee('data-ai-thinking-option', false)
            ->assertSee('value="low" data-ai-thinking-option checked', false)
            ->assertSeeInOrder(['class="ai-composer-send"', 'data-ai-thinking', 'data-ai-send'], false)
            ->assertSee('id="ai-input-hint" class="sr-only"', false)
            ->assertSee(route('ai.settings.thinking.update'), false);
    }

    public function test_history_displays_latest_messages_in_order_and_excludes_failed_messages(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Riwayat panjang']);
        for ($i = 0; $i < 102; $i++) {
            $session->messages()->create([
                'role' => 'user', 'content' => 'Pesan urutan '.$i.' selesai', 'status' => 'completed',
                'created_at' => now()->subMinutes(102 - $i),
            ]);
        }
        $session->messages()->create(['role' => 'user', 'content' => 'Pesan gagal tersembunyi', 'status' => 'failed']);

        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()
            ->assertDontSee('Pesan urutan 0 selesai')
            ->assertDontSee('Pesan urutan 1 selesai')
            ->assertDontSee('Pesan gagal tersembunyi')
            ->assertSeeInOrder(['Pesan urutan 2 selesai', 'Pesan urutan 101 selesai']);
    }

    public function test_history_items_expose_keyboard_accessible_session_actions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Rencana semester']);

        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()
            ->assertSee('data-ai-history-menu-trigger', false)
            ->assertSee('aria-label="Opsi untuk Rencana semester"', false)
            ->assertSee('data-ai-history-action="rename"', false)
            ->assertSee('data-ai-history-action="delete"', false);
    }

    public function test_confirmed_proposal_keeps_its_persisted_status_on_reload(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Proposal tersimpan']);
        $proposal = AiActionProposal::create([
            'user_id' => $user->id, 'session_id' => $session->id, 'action_id' => 'act_view_test',
            'tool_name' => 'create_todolist', 'summary' => 'Baca materi', 'payload_json' => ['nama_item' => 'Baca materi'],
            'signature' => str_repeat('a', 64), 'status' => 'confirmed', 'expires_at' => now()->addMinutes(15),
        ]);
        $session->messages()->create([
            'role' => 'assistant', 'content' => 'Usulan siap.',
            'tool_calls_json' => [['action_id' => $proposal->action_id, 'signature' => $proposal->signature,
                'summary' => $proposal->summary, 'expires_at' => $proposal->expires_at->toIso8601String()]],
        ]);
        $response = $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()
            ->assertSee('data-status="confirmed"', false)
            ->assertSee('Sudah disimpan')
            ->assertSee('Item to-do berhasil disimpan.')
            ->assertSee('Buka To-Do')
            ->assertSee('data-action-acknowledgements', false);
        $this->assertMatchesRegularExpression('/data-proposal-actions\s+hidden/', $response->getContent());
    }

    public function test_assistant_markdown_is_rendered_safely_when_history_is_loaded(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Markdown']);
        $session->messages()->create([
            'role' => 'assistant',
            'content' => '**Jadwal aman** <script>alert("xss")</script>',
            'status' => 'completed',
        ]);

        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()
            ->assertSee('<strong>Jadwal aman</strong>', false)
            ->assertDontSee('alert("xss")', false);
    }

    public function test_loaded_user_message_does_not_preserve_blade_indentation_as_bubble_whitespace(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Pesan ringkas']);
        $session->messages()->create([
            'role' => 'user',
            'content' => 'Apa jadwal saya?',
            'status' => 'completed',
        ]);

        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()
            ->assertSee('<div class="ai-message-text" data-message-text>Apa jadwal saya?</div>', false);
    }

    public function test_code_artifact_is_rendered_in_history_with_a_sandboxed_html_preview(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Kode HTML']);
        $session->messages()->create([
            'role' => 'assistant',
            'content' => "```html\n<!doctype html><title>Demo</title><h1>Aman</h1>\n```",
            'status' => 'completed',
        ]);

        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()
            ->assertSee('data-code-artifact', false)
            ->assertSee('data-code-copy', false)
            ->assertSee('data-code-download', false)
            ->assertSee('data-code-run', false)
            ->assertSee('data-code-preview', false)
            ->assertSee('sandbox="allow-scripts"', false)
            ->assertDontSee('allow-same-origin', false)
            ->assertDontSee('<h1>Aman</h1>', false);
    }

    public function test_workspace_resumes_an_active_run_with_inline_thinking_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Masih berpikir']);
        $branch = $session->branches()->create();
        $session->update(['active_branch_id' => $branch->id]);
        $message = $session->messages()->create([
            'branch_id' => $branch->id,
            'role' => 'user',
            'content' => 'Besok ngapain ya?',
            'status' => 'pending',
        ]);
        $branch->update(['head_message_id' => $message->id]);
        $run = AiChatRun::create([
            'session_id' => $session->id,
            'branch_id' => $branch->id,
            'user_message_id' => $message->id,
            'status' => 'running',
            'started_at' => now(),
            'lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($user)->get(route('ai.workspace', ['session' => $session->id]))
            ->assertOk()
            ->assertSee('data-active-run-id="'.$run->id.'"', false)
            ->assertSee('Besok ngapain ya?')
            ->assertSee('data-ai-thinking-indicator', false)
            ->assertSee('Memproses permintaan')
            ->assertDontSee('data-ai-thinking-copy>Thinking', false)
            ->assertSee('data-ai-tool-icon-template', false)
            ->assertSee('data-ai-effort-slider', false)
            ->assertSee('type="range" min="0" max="3" step="1"', false)
            ->assertSee('data-ai-stop-icon', false)
            ->assertDontSee('sedang menyiapkan jawaban');
    }
}
