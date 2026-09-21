<?php

namespace App\Services\Ai\Science;

use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmToolCall;
use Illuminate\Validation\ValidationException;

/** JSON-only, versioned continuation; encrypted by the execution repository. */
final class ScienceCheckpoint
{
    public static function encode(array $state): array
    {
        $state['version'] = 1;
        $state['history'] = array_map(fn (LlmMessage $message) => $message->toArray(), $state['history']);
        if (strlen(json_encode($state, JSON_THROW_ON_ERROR)) > 524288) {
            throw ValidationException::withMessages(['science_checkpoint' => 'Continuation context exceeds its resource limit.']);
        }

        return $state;
    }

    public static function decode(array $state): array
    {
        if (($state['version'] ?? null) !== 1) {
            throw ValidationException::withMessages(['science_checkpoint' => 'Unsupported continuation version.']);
        }
        $state['history'] = array_map(fn ($message) => new LlmMessage($message['role'], $message['content'] ?? null,
            array_map(fn ($call) => new LlmToolCall($call['id'], $call['name'], $call['arguments'], $call['argument_error'] ?? null), $message['tool_calls'] ?? []),
            $message['tool_result'] ?? null, $message['reasoning_content'] ?? null), $state['history']);

        return $state;
    }
}
