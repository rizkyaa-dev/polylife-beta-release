<?php

namespace Tests\Feature\Ai;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rename_an_owned_chat_session(): void
    {
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Judul lama']);

        $this->actingAs($user)
            ->patchJson(route('ai.sessions.update', $session), ['title' => '  Judul baru  '])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('session.title', 'Judul baru');

        $this->assertDatabaseHas('ai_chat_sessions', [
            'id' => $session->id,
            'user_id' => $user->id,
            'title' => 'Judul baru',
        ]);
    }

    public function test_blank_chat_session_title_is_rejected_without_changing_data(): void
    {
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Tetap sama']);

        $this->actingAs($user)
            ->patchJson(route('ai.sessions.update', $session), ['title' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseHas('ai_chat_sessions', [
            'id' => $session->id,
            'title' => 'Tetap sama',
        ]);
    }

    public function test_user_cannot_rename_or_delete_another_users_chat_session(): void
    {
        $owner = $this->activeUser();
        $intruder = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $owner->id, 'title' => 'Percakapan privat']);

        $this->actingAs($intruder)
            ->patchJson(route('ai.sessions.update', $session), ['title' => 'Diambil alih'])
            ->assertNotFound();
        $this->actingAs($intruder)
            ->deleteJson(route('ai.sessions.destroy', $session))
            ->assertNotFound();

        $this->assertDatabaseHas('ai_chat_sessions', [
            'id' => $session->id,
            'user_id' => $owner->id,
            'title' => 'Percakapan privat',
        ]);
    }

    public function test_user_can_delete_an_owned_chat_session_and_its_messages(): void
    {
        $user = $this->activeUser();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Akan dihapus']);
        $message = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => 'Isi percakapan',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('ai.sessions.destroy', $session))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'session_id' => $session->id,
            ]);

        $this->assertDatabaseMissing('ai_chat_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('ai_chat_messages', ['id' => $message->id]);
    }

    private function activeUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
            'account_status' => 'active',
        ]);
    }
}
