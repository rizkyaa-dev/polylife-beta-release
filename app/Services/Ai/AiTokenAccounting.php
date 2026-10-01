<?php

namespace App\Services\Ai;

use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Services\Ai\DTOs\LlmTokenUsage;
use Illuminate\Support\Facades\DB;

final class AiTokenAccounting
{
    public function record(int $runId, int $stepId, int $attempt, ?LlmTokenUsage $usage): void
    {
        // Persist before validation/cancellation. A billed late response still counts,
        // but a previous worker attempt cannot affect the current execution.
        DB::transaction(function () use ($runId, $stepId, $attempt, $usage): void {
            $run = AiChatRun::query()->lockForUpdate()->find($runId);
            if (! $run || $run->attempts !== $attempt) {
                return;
            }
            $step = AiChatRunStep::query()->where('run_id', $runId)->lockForUpdate()->findOrFail($stepId);
            $metadata = $step->public_metadata ?? [];
            $stepUsage = LlmTokenUsage::fromArray($metadata['tokens'] ?? null) ?? new LlmTokenUsage;
            $metadata['model_calls'] = ($metadata['model_calls'] ?? 0) + 1;
            // Partial reports contribute counters without certifying a full call.
            $measured = $usage?->isComplete ? 1 : 0;
            $metadata['measured_model_calls'] = ($metadata['measured_model_calls'] ?? 0) + $measured;
            if ($usage !== null) {
                $metadata['tokens'] = $stepUsage->accumulate($usage)->toArray();
            }
            $step->update(['public_metadata' => $metadata]);
            $total = $run->tokenUsage()->accumulate($usage);
            $run->update([
                'model_calls' => $run->model_calls + 1,
                'measured_model_calls' => $run->measured_model_calls + $measured,
                'prompt_tokens' => $total->promptTokens,
                'completion_tokens' => $total->completionTokens,
                'total_tokens' => $total->totalTokens,
            ]);
        }, 3);
    }
}
