<?php

namespace App\Services\Ai;

use App\Models\AiChatRun;
use Illuminate\Support\Facades\DB;

final class AiRunStateManager
{
    public function expire(AiChatRun|int $run): void
    {
        $runId = $run instanceof AiChatRun ? $run->id : $run;
        DB::transaction(function () use ($runId): void {
            $lockedRun = AiChatRun::query()->lockForUpdate()->find($runId);
            if (! $lockedRun || $lockedRun->status !== 'running'
                || ! $lockedRun->lease_expires_at?->isPast()) {
                return;
            }
            $this->transitionToFailed($lockedRun, 'run_lease_expired', true);
        });
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

            $this->transitionToFailed($lockedRun, $errorCode, $retryable, $durationMs);
        });
    }

    /**
     * Atomically fail a run that was dispatched but never claimed by a worker.
     * The heartbeat is checked again under a row lock to avoid racing a worker.
     */
    public function failIfWorkerDidNotStart(AiChatRun|int $run): bool
    {
        $runId = $run instanceof AiChatRun ? $run->id : $run;
        $configuredTimeout = max(30, (int) config('services.ai_worker_start_timeout_seconds', 300));
        $timeoutSeconds = app()->environment('local') ? min(30, $configuredTimeout) : $configuredTimeout;
        $cutoff = now()->subSeconds($timeoutSeconds);

        return DB::transaction(function () use ($runId, $cutoff): bool {
            $lockedRun = AiChatRun::query()->lockForUpdate()->find($runId);

            if (! $lockedRun
                || $lockedRun->status !== 'running'
                || $lockedRun->heartbeat_at !== null
                || $lockedRun->last_dispatched_at === null
                || $lockedRun->last_dispatched_at->isAfter($cutoff)) {
                return false;
            }

            $this->transitionToFailed($lockedRun, 'worker_start_timeout', true);

            return true;
        });
    }

    private function transitionToFailed(
        AiChatRun $run,
        string $errorCode,
        bool $retryable,
        ?int $durationMs = null
    ): void {
        $run->load(['session', 'branch', 'userMessage']);
        $run->userMessage?->update(['status' => 'failed', 'error_code' => $errorCode]);
        $run->steps()->where('status', 'running')->update(['status' => 'failed']);
        $run->update([
            'status' => 'failed',
            'duration_ms' => $durationMs,
            'error_code' => $errorCode,
            'retryable' => $retryable,
            'completed_at' => now(),
            'heartbeat_at' => now(),
            'lease_expires_at' => null,
        ]);

        if ($run->branch && $run->userMessage
            && (int) $run->branch->head_message_id === (int) $run->userMessage->id) {
            $run->branch->update(['head_message_id' => $run->userMessage->parent_message_id]);
        }
        if ($run->session && $run->previous_branch_id
            && (int) $run->session->active_branch_id === (int) $run->branch_id) {
            $run->session->update(['active_branch_id' => $run->previous_branch_id]);
        }
    }
}
