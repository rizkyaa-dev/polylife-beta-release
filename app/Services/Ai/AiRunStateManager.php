<?php

namespace App\Services\Ai;

use App\Models\AiChatRun;
use Illuminate\Support\Facades\DB;

final class AiRunStateManager
{
    public function expire(AiChatRun|int $run): void
    {
        $this->fail($run, 'run_lease_expired', true);
    }

    public function expireStale(int $limit = 100): int
    {
        $runIds = AiChatRun::query()
            ->where('status', 'running')
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', now())
            ->oldest('lease_expires_at')
            ->limit(max(1, $limit))
            ->pluck('id');

        foreach ($runIds as $runId) {
            $this->expire((int) $runId);
        }

        return $runIds->count();
    }

    public function fail(AiChatRun|int $run, string $errorCode, bool $retryable, ?int $durationMs = null): void
    {
        $runId = $run instanceof AiChatRun ? $run->id : $run;

        DB::transaction(function () use ($runId, $errorCode, $retryable, $durationMs): void {
            $lockedRun = AiChatRun::query()->lockForUpdate()->find($runId);
            if (! $lockedRun || $lockedRun->status !== 'running') {
                return;
            }
            $lockedRun->load(['session', 'branch', 'userMessage']);
            $lockedRun->userMessage?->update(['status' => 'failed', 'error_code' => $errorCode]);
            $lockedRun->steps()->where('status', 'running')->update(['status' => 'failed']);
            $lockedRun->update([
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'error_code' => $errorCode,
                'retryable' => $retryable,
                'completed_at' => now(),
                'heartbeat_at' => now(),
                'lease_expires_at' => null,
            ]);

            if ($lockedRun->branch && $lockedRun->userMessage
                && (int) $lockedRun->branch->head_message_id === (int) $lockedRun->userMessage->id) {
                $lockedRun->branch->update(['head_message_id' => $lockedRun->userMessage->parent_message_id]);
            }
            if ($lockedRun->session && $lockedRun->previous_branch_id
                && (int) $lockedRun->session->active_branch_id === (int) $lockedRun->branch_id) {
                $lockedRun->session->update(['active_branch_id' => $lockedRun->previous_branch_id]);
            }
        });
    }
}
