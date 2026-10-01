<?php

namespace App\Services\Ai;

use App\Models\AiChatMessage;
use App\Models\AiChatRun;

final class AiTokenUsageSummary
{
    public static function run(AiChatRun $run): array
    {
        return [
            'prompt' => (int) $run->prompt_tokens,
            'completion' => (int) $run->completion_tokens,
            'total' => (int) $run->total_tokens,
            'status' => $run->tokenUsageStatus(),
        ];
    }

    public static function session(?int $sessionId): array
    {
        // Legacy answers may predate run tracking; complete runs alone do not cover them.
        $untrackedAnswers = AiChatMessage::query()->selectRaw('1')
            ->where('session_id', $sessionId)
            ->where('role', 'assistant')->where('status', 'completed')
            ->whereDoesntHave('run', fn ($query) => $query->where('session_id', $sessionId))
            ->limit(1);

        $row = AiChatRun::query()->where('session_id', $sessionId)
            ->where(fn ($query) => $query->where('status', 'completed')->orWhere('model_calls', '>', 0))
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) AS prompt, COALESCE(SUM(completion_tokens), 0) AS completion, COALESCE(SUM(total_tokens), 0) AS total, COALESCE(SUM(measured_model_calls), 0) AS measured, COUNT(*) AS runs, COALESCE(SUM(CASE WHEN model_calls = 0 OR measured_model_calls < model_calls THEN 1 ELSE 0 END), 0) AS incomplete')
            ->selectSub($untrackedAnswers, 'has_untracked_answers')
            ->first();

        return [
            'prompt' => (int) $row->prompt,
            'completion' => (int) $row->completion,
            'total' => (int) $row->total,
            'status' => $row->runs > 0 && (int) $row->incomplete === 0 && ! $row->has_untracked_answers
                ? 'complete' : ($row->measured > 0 || $row->total > 0 ? 'partial' : 'unknown'),
        ];
    }
}
