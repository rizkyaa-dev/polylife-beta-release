<?php

namespace App\Services\Ai;

use App\Jobs\ProcessAiChatRun;
use App\Models\AiChatRun;
use App\Services\Ai\Enums\ThinkingEffort;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AiRunDispatcher
{
    public function dispatch(int $runId, ThinkingEffort $effort = ThinkingEffort::High): void
    {
        $connection = $this->connection();
        $connections = (array) config('queue.connections', []);

        if ($connection === '' || ! array_key_exists($connection, $connections)) {
            throw new InvalidArgumentException("Koneksi queue AI '{$connection}' tidak valid.");
        }

        ProcessAiChatRun::dispatch($runId)
            ->onConnection($connection)
            ->onQueue($this->queueFor($effort))
            ->afterCommit();

        AiChatRun::query()->whereKey($runId)->update([
            'last_dispatched_at' => now(),
            'dispatch_attempts' => DB::raw('dispatch_attempts + 1'),
        ]);
    }

    public function redispatchOrphaned(int $limit = 100): int
    {
        $cutoff = now()->subSeconds(max(30, (int) config('services.ai_redispatch_after_seconds', 120)));
        $runs = AiChatRun::query()
            ->where('status', 'running')
            ->whereNull('heartbeat_at')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_dispatched_at')
                    ->orWhere('last_dispatched_at', '<=', $cutoff);
            })
            ->with('session.user.aiAssistant')
            ->oldest('id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($runs as $run) {
            $effort = $run->session?->user?->aiAssistant?->thinking_effort;
            $this->dispatch(
                $run->id,
                $effort instanceof ThinkingEffort ? $effort : ThinkingEffort::High
            );
        }

        return $runs->count();
    }

    private function queueFor(ThinkingEffort $effort): string
    {
        return match ($effort) {
            ThinkingEffort::Off, ThinkingEffort::Low => (string) config('services.ai_fast_queue', 'ai-fast'),
            ThinkingEffort::High, ThinkingEffort::Max => (string) config('services.ai_heavy_queue', 'ai-heavy'),
        };
    }

    /** Long inference must never run inside the HTTP request lifecycle. */
    public function connection(): string
    {
        $configured = trim((string) config('services.ai_queue_connection'));
        $connection = $configured !== '' ? $configured
            : (app()->environment('local') ? 'database' : trim((string) config('queue.default')));
        if (! array_key_exists($connection, (array) config('queue.connections'))) {
            throw new InvalidArgumentException("Koneksi queue AI '{$connection}' tidak valid.");
        }
        if (! app()->environment('testing') && ! in_array(config("queue.connections.{$connection}.driver"), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            throw new InvalidArgumentException('AI membutuhkan queue persisten dengan worker terpisah; sync/deferred/background tidak didukung.');
        }

        return $connection;
    }
}
