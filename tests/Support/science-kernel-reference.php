<?php

use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

/** Test-only batch oracle: no model/provider calls or persistent records. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cases = json_decode(stream_get_contents(STDIN, 1048576), true, 32, JSON_THROW_ON_ERROR);
$results = [];
foreach ($cases as $case) {
    try {
        $results[] = app(ScienceSolverRegistry::class)->solve($case['solver'], $case['inputs']);
    } catch (ValidationException $exception) {
        $results[] = ['error' => 'invalid_input'];
    }
}
echo json_encode($results, JSON_THROW_ON_ERROR);
