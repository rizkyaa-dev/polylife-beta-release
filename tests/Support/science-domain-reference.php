<?php

use App\Services\Ai\Science\Models\ScienceModelRegistry;
use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Contracts\Console\Kernel;

// Fixed fixture only; no provider calls, arbitrary paths or persistent records.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/science-rc-domain.json'), true, 32, JSON_THROW_ON_ERROR);
$plan = app(ScienceModelRegistry::class)->prepare(['status' => 'ready', 'solver' => 'ode_ivp',
    'domain_model' => $fixture['domain_model'], 'model_source' => $fixture['source']]);
$report = ['solver' => $plan['solver'], 'inputs' => $plan['solver_inputs'],
    'reference' => app(ScienceSolverRegistry::class)->solve($plan['solver'], $plan['solver_inputs'])];
if (($argv[1] ?? null) === '--benchmark') {
    $times = [];
    $registry = app(ScienceModelRegistry::class);
    $solvers = app(ScienceSolverRegistry::class);
    $started = hrtime(true);
    for ($index = 0; $index < 1000; $index++) {
        $jobStarted = hrtime(true);
        $prepared = $registry->prepare($plan);
        $solvers->validateContract($prepared['solver'], $prepared['solver_inputs']);
        $times[] = (hrtime(true) - $jobStarted) / 1e6;
    }
    $duration = (hrtime(true) - $started) / 1e6;
    sort($times);
    $report['benchmark'] = ['scope' => 'Sequential fixed-fixture domain preparation and structural preflight, not inference or concurrent user throughput.',
        'jobs' => 1000, 'duration_ms' => $duration, 'p95_ms' => $times[949], 'peak_memory_bytes' => memory_get_peak_usage(true)];
}
echo json_encode($report, JSON_THROW_ON_ERROR);
