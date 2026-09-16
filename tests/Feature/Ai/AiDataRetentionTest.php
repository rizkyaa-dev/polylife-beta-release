<?php

namespace Tests\Feature\Ai;

use App\Models\AiActionProposal;
use App\Models\AiChatMessage;
use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\AiDataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_pending_proposals_and_redacts_old_sensitive_payloads(): void
    {
        config(['services.ai_sensitive_data_retention_days' => 30]);
        $user = User::factory()->create();
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Retensi']);
        $old = now()->subDays(31);
        $message = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'status' => 'completed',
            'content' => 'Jawaban tetap tersedia',
            'reasoning_content' => 'rahasia lama',
            'tool_calls_json' => [[
                'action_id' => 'act_old',
                'summary' => 'Proposal lama',
                'payload' => ['secret' => 'hapus'],
                'signature' => str_repeat('a', 64),
            ]],
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        DB::table('ai_chat_messages')->where('id', $message->id)->update([
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        $run = AiChatRun::create([
            'session_id' => $session->id,
            'branch_id' => 1,
            'user_message_id' => 1,
            'assistant_message_id' => $message->id,
            'status' => 'completed',
            'started_at' => $old,
            'completed_at' => $old,
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        $step = AiChatRunStep::create([
            'run_id' => $run->id,
            'sequence' => 1,
            'kind' => 'tool_call',
            'status' => 'completed',
            'label' => 'Data lama',
            'private_payload' => ['secret' => 'hapus'],
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        DB::table('ai_chat_run_steps')->where('id', $step->id)->update([
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        $confirmed = AiActionProposal::create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'action_id' => 'act_old',
            'tool_name' => 'create_catatan',
            'summary' => 'Proposal lama',
            'payload_json' => ['secret' => 'hapus'],
            'signature' => str_repeat('a', 64),
            'status' => 'confirmed',
            'expires_at' => $old,
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        DB::table('ai_action_proposals')->where('id', $confirmed->id)->update([
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        $pending = AiActionProposal::create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'action_id' => 'act_expired',
            'tool_name' => 'create_catatan',
            'summary' => 'Kadaluarsa',
            'payload_json' => [],
            'signature' => str_repeat('b', 64),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        app(AiDataRetentionService::class)->prune();

        $this->assertNull($message->fresh()->reasoning_content);
        $this->assertNull($step->fresh()->private_payload);
        $this->assertSame([], $confirmed->fresh()->payload_json);
        $this->assertArrayNotHasKey('payload', $message->fresh()->tool_calls_json[0]);
        $this->assertArrayNotHasKey('signature', $message->fresh()->tool_calls_json[0]);
        $this->assertSame('expired', $pending->fresh()->status);
    }
}
