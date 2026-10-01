<?php

namespace App\Services\Ai;

use App\Models\AiChatRun;

final class AiCodeArtifactManifest
{
    public static function forRun(?AiChatRun $run): ?array
    {
        if (! $run) {
            return null;
        }
        $run->loadMissing('steps');
        $step = $run->steps->last(fn ($step) => $step->tool_name === AiCodingDelegation::TOOL_NAME && $step->status === 'completed');

        return $step?->private_payload['coding_brief'] ?? null;
    }
}
