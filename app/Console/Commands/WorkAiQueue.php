<?php

namespace App\Console\Commands;

use App\Services\Ai\AiRunDispatcher;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class WorkAiQueue extends Command
{
    protected $signature = 'ai:work {--once : Process at most one job}';

    protected $description = 'Run the worker on the same connection and queues used by AI dispatch';

    public function handle(AiRunDispatcher $dispatcher): int
    {
        try {
            $connection = $dispatcher->connection();
            if (! array_key_exists($connection, (array) config('queue.connections'))) {
                throw new InvalidArgumentException('Koneksi queue AI tidak valid.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        // CLI execution has its own timeout, independent of the HTTP PHP limit.
        $queues = array_unique([
            (string) config('services.ai_fast_queue', 'ai-fast'),
            (string) config('services.ai_heavy_queue', 'ai-heavy'), 'default',
        ]);
        $options = ['connection' => $connection, '--queue' => implode(',', $queues), '--tries' => 1, '--timeout' => 510];
        if ($this->option('once')) {
            $options['--once'] = true;

            return $this->call('queue:work', $options);
        }

        return $this->call(app()->environment('local') ? 'queue:listen' : 'queue:work', $options);
    }
}
