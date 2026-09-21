<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\OdeSolver;
use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceOdeTest extends TestCase
{
    public function test_exponential_decay_matches_independent_analytic_solution(): void
    {
        $result = app(OdeSolver::class)->solve(['initial' => [2], 'derivatives' => [
            ['op' => 'mul', 'args' => [['op' => 'const', 'value' => -0.5], ['op' => 'var', 'name' => 'y0']]],
        ], 't_start' => 0, 't_end' => 10, 'tolerance' => 1e-8]);
        $this->assertSame('computed', $result['status']);
        $this->assertEqualsWithDelta(2 * exp(-5), $result['final_state'][0], 1e-7);
        $this->assertLessThanOrEqual(65, count($result['samples']));
        $this->assertEqualsWithDelta(10, end($result['samples'])['t'], 1e-12);
    }

    public function test_damped_oscillator_matches_analytic_solution(): void
    {
        $result = app(OdeSolver::class)->solve(['initial' => [1, 0], 'derivatives' => [
            ['op' => 'var', 'name' => 'y1'],
            ['op' => 'sub', 'args' => [
                ['op' => 'mul', 'args' => [['op' => 'const', 'value' => -4], ['op' => 'var', 'name' => 'y0']]],
                ['op' => 'mul', 'args' => [['op' => 'const', 'value' => 0.4], ['op' => 'var', 'name' => 'y1']]],
            ]],
        ], 't_start' => 0, 't_end' => 5, 'tolerance' => 1e-8]);
        $omega = sqrt(3.96);
        $expected = exp(-1) * (cos($omega * 5) + 0.2 / $omega * sin($omega * 5));
        $expectedVelocity = -4 / $omega * exp(-1) * sin($omega * 5);
        $this->assertSame('computed', $result['status']);
        $this->assertEqualsWithDelta($expected, $result['final_state'][0], 1e-6);
        $this->assertEqualsWithDelta($expectedVelocity, $result['final_state'][1], 1e-6);
        $this->assertSame('estimated_local_error_only', $result['verification']['status']);
    }

    public function test_stiff_case_is_bounded_and_never_publishes_partial_state(): void
    {
        $result = app(OdeSolver::class)->solve(['initial' => [1], 'derivatives' => [
            ['op' => 'mul', 'args' => [['op' => 'const', 'value' => -1e6], ['op' => 'var', 'name' => 'y0']]],
        ], 't_start' => 0, 't_end' => 1]);
        $this->assertSame('not_converged', $result['status']);
        $this->assertNull($result['final_state']);
        $this->assertSame([], $result['samples']);
        $this->assertLessThanOrEqual(2048, $result['attempts']);
    }

    public function test_resonant_forcing_matches_analytic_position_and_velocity_at_every_sample(): void
    {
        $result = app(OdeSolver::class)->solve(['initial' => [0, 0], 'derivatives' => [
            ['op' => 'var', 'name' => 'y1'],
            ['op' => 'sub', 'args' => [
                ['op' => 'mul', 'args' => [['op' => 'const', 'value' => 3], ['op' => 'sin', 'args' => [
                    ['op' => 'mul', 'args' => [['op' => 'const', 'value' => 2], ['op' => 'var', 'name' => 't']]],
                ]]]],
                ['op' => 'mul', 'args' => [['op' => 'const', 'value' => 4], ['op' => 'var', 'name' => 'y0']]],
            ]],
        ], 't_start' => 0, 't_end' => 5, 'tolerance' => 1e-8]);
        $this->assertSame('computed', $result['status']);
        foreach ($result['samples'] as $sample) {
            $time = $sample['t'];
            $this->assertEqualsWithDelta(3 / 8 * sin(2 * $time) - 3 / 4 * $time * cos(2 * $time), $sample['state'][0], 1e-6);
            $this->assertEqualsWithDelta(3 / 2 * $time * sin(2 * $time), $sample['state'][1], 1e-6);
        }
    }

    public function test_unknown_state_variables_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(OdeSolver::class)->solve(['initial' => [1], 'derivatives' => [['op' => 'var', 'name' => 'y3']], 't_start' => 0, 't_end' => 1]);
    }

    public function test_equilibrium_power_continuation_matches_the_exact_family_without_inventing_time_dynamics(): void
    {
        $fixtures = json_decode(file_get_contents(base_path('tests/Fixtures/science-kernel.json')), true, flags: JSON_THROW_ON_ERROR);
        $fixture = collect($fixtures)->firstWhere('name', 'radiative-power-continuation');
        $result = app(ScienceSolverRegistry::class)->solve($fixture['solver'], $fixture['inputs']);
        $this->assertSame('computed', $result['status']);
        $this->assertSame('declared_dimensions_checked', $result['dimension_verification']['status']);
        foreach ($result['samples'] as $sample) {
            $expected = ($sample['t'] / (0.8 * 5.670374419e-8 * 0.01) + 300 ** 4) ** 0.25;
            $this->assertEqualsWithDelta($expected, $sample['state'][0], 1e-5);
        }
    }

    public function test_sample_times_do_not_drift_or_omit_the_final_state(): void
    {
        foreach ([[0.1, 0.3], [1e12, 1e12 + 1]] as [$start, $end]) {
            $result = app(OdeSolver::class)->solve(['initial' => [0], 'derivatives' => [['op' => 'const', 'value' => 1]],
                't_start' => $start, 't_end' => $end]);
            $this->assertSame('computed', $result['status']);
            $this->assertCount(65, $result['samples']);
            $this->assertSame($end, end($result['samples'])['t']);
            $this->assertEqualsWithDelta($end - $start, $result['final_state'][0], 1e-10);
            for ($i = 1; $i < count($result['samples']); $i++) {
                $this->assertGreaterThan($result['samples'][$i - 1]['t'], $result['samples'][$i]['t']);
            }
        }
    }
}
