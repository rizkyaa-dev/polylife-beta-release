<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Science\ScienceCheckpoint;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceCheckpointTest extends TestCase
{
    public function test_json_round_trip_preserves_tool_identity_reasoning_and_argument_errors(): void
    {
        $messages = [new LlmMessage('user', 'Rangkaian dengan μF'),
            new LlmMessage('assistant', toolCalls: [new LlmToolCall('call-1', 'delegate_science_problem', ['problem' => 'Model'], 'Invalid original arguments')], reasoningContent: 'Bounded summary'),
            new LlmMessage('tool', toolResult: ['call_id' => 'call-1', 'result' => ['status' => 'unsupported']])];
        $encoded = ScienceCheckpoint::encode(['history' => $messages, 'iterations' => 3, 'run_budget_seconds' => 240]);
        $decoded = ScienceCheckpoint::decode(json_decode(json_encode($encoded, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(3, $decoded['iterations']);
        $this->assertSame(240, $decoded['run_budget_seconds']);
        $this->assertEquals($messages, $decoded['history']);
    }

    public function test_checkpoint_rejects_unknown_versions(): void
    {
        $this->expectException(ValidationException::class);
        ScienceCheckpoint::decode(['version' => 2, 'history' => []]);
    }

    public function test_checkpoint_rejects_excessive_context_instead_of_truncating_it(): void
    {
        $this->expectException(ValidationException::class);
        ScienceCheckpoint::encode(['history' => [new LlmMessage('user', str_repeat('x', 524288))]]);
    }
}
