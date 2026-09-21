<?php

namespace App\Services\Ai\Science\Client;

use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Anonymous aggregates only: no user IDs, source text, scripts or computed values. */
final class ScienceCapabilityTelemetry
{
    public const CAPABILITIES = ['unregistered_computation', 'stiff_ode', 'pde_dae', 'large_linear_system',
        'sparse_algebra', 'continuation', 'eigenvalues', 'sensitivity', 'parameter_estimation', 'optimization', 'unsupported_domain'];

    public function record(string $outcome, array $capabilities = []): void
    {
        if (! config('services.ai_science_capability_telemetry_enabled', false)) {
            return;
        }
        $column = match ($outcome) {
            'unsupported' => 'unsupported_count', 'prepared' => 'prepared_count',
            'client_computed' => 'client_result_count', default => 'failure_count',
        };
        $capabilities = array_slice(array_values(array_unique(array_intersect($capabilities, self::CAPABILITIES))), 0, 8);
        try {
            foreach ($capabilities ?: ['unregistered_computation'] as $capability) {
                $key = ['kernel_version' => ScienceSolverRegistry::KERNEL_VERSION, 'capability' => $capability];
                DB::table('ai_science_capability_gaps')->insertOrIgnore($key + ['created_at' => now(), 'updated_at' => now()]);
                DB::table('ai_science_capability_gaps')->where($key)->increment($column, 1, ['updated_at' => now()]);
            }
        } catch (QueryException) {
            // Optional anonymous metrics must not break a scientific response.
            Log::warning('ai.science.capability_telemetry_unavailable');
        }
    }
}
