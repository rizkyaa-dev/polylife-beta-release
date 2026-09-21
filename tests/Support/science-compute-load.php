<?php

use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Contracts\Console\Kernel;

// Local deterministic compute benchmark, not a claim about concurrent HTTP users.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
$count = (int) ($argv[1] ?? 1000);
if ($count < 1 || $count > 10000) {
    throw new InvalidArgumentException('Job count must be 1..10000.');
}
$solver = app(ScienceSolverRegistry::class);
$n = 8;
$matrix = [];
$expected = array_map(fn ($i) => sin($i + 1), range(0, $n - 1));
$rhs = [];
for ($i = 0; $i < $n; $i++) {
    $matrix[$i] = [];
    $rhs[$i] = 0;
    for ($j = 0; $j < $n; $j++) {
        $coefficient = $i === $j ? 6 : (abs($i - $j) === 1 ? -1 : 0);
        $matrix[$i][$j] = $coefficient;
        $rhs[$i] += $coefficient * $expected[$j];
    }
}
$times = [];
$started = hrtime(true);
for ($job = 0; $job < $count; $job++) {
    $before = hrtime(true);
    $result = $solver->solve('linear_system', compact('matrix', 'rhs'));
    $times[] = (hrtime(true) - $before) / 1e6;
    foreach ($result['solution'] as $i => $value) {
        if (abs($value - $expected[$i]) > 1e-10) {
            throw new RuntimeException('Reference solution mismatch.');
        }
    }
}
$elapsed = (hrtime(true) - $started) / 1e6;
sort($times);
echo json_encode(['mode' => 'sequential_local_compute', 'jobs' => $count, 'dimension' => $n,
    'elapsed_ms' => $elapsed, 'p95_ms' => $times[(int) floor(0.95 * ($count - 1))],
    'peak_process_memory_bytes' => memory_get_peak_usage(true), 'correctness' => 'all_reference_vectors_passed'], JSON_THROW_ON_ERROR).PHP_EOL;
