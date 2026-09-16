<?php

namespace App\Console\Commands;

use App\Services\Ai\Benchmark\AiBenchmarkRunner;
use Illuminate\Console\Command;

class BenchmarkAiAgent extends Command
{
    protected $signature = 'ai:benchmark {--case= : Jalankan satu ID skenario saja}';

    protected $description = 'Benchmark perilaku AI agent menggunakan provider aktif dan fixture yang di-rollback';

    public function handle(AiBenchmarkRunner $runner): int
    {
        $this->warn('Benchmark memakai provider AI aktif dan dapat menggunakan kuota API.');
        $results = $runner->run(
            $this->option('case') ?: null,
            function (array $result): void {
                $status = $result['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
                $tools = $result['tools'] === [] ? '-' : implode(', ', $result['tools']);
                $attempts = ($result['provider_attempts'] ?? 1) > 1 ? '  retries: '.($result['provider_attempts'] - 1) : '';
                $this->line(sprintf('%s %-28s %6d ms  tools: %s%s', $status, $result['id'], $result['duration_ms'], $tools, $attempts));
                if ($result['error']) {
                    $this->error($result['error']);
                }
                $this->line('  '.str($result['reply'])->squish()->limit(320));
            }
        );

        if ($results === []) {
            $this->error('Skenario benchmark tidak ditemukan.');

            return self::INVALID;
        }

        $passed = collect($results)->where('passed', true)->count();
        $this->newLine();
        $this->info("Hasil: {$passed}/".count($results).' skenario lulus.');

        return $passed === count($results) ? self::SUCCESS : self::FAILURE;
    }
}
