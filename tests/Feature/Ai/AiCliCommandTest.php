<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Providers\MockLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCliCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cli_can_run_production_orchestrator_for_active_workspace_user(): void
    {
        $user = User::factory()->create(['account_status' => 'active', 'role' => 'user']);
        $client = new MockLlmClient;
        $client->queueResponse(new LlmResponse(content: 'Jawaban CLI berhasil.'));
        $this->app->instance(LlmClientInterface::class, $client);

        $this->artisan('ai:chat', [
            'user' => (string) $user->id,
            'prompt' => ['Jelaskan', 'jadwal', 'saya'],
        ])->expectsOutputToContain('Jawaban CLI berhasil.')
            ->expectsOutputToContain('Tools: -')
            ->assertSuccessful();

        $this->assertSame(1, AiChatSession::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, AiChatMessage::query()->whereHas('session', fn ($query) => $query->where('user_id', $user->id))->count());
    }

    public function test_cli_rejects_inactive_user_before_calling_provider(): void
    {
        $user = User::factory()->create(['account_status' => 'banned', 'role' => 'user']);

        $this->artisan('ai:chat', [
            'user' => $user->email,
            'prompt' => ['Halo'],
        ])->expectsOutput('AI workspace hanya dapat dijalankan untuk akun pengguna aktif.')
            ->assertFailed();

        $this->assertDatabaseCount('ai_chat_sessions', 0);
    }
}
