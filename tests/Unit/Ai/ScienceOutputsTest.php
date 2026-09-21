<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceOutputsTest extends TestCase
{
    private function input(): array
    {
        $kelvinFourth = [0, 0, 0, 0, 4, 0, 0];

        return ['matrix' => [[1]], 'rhs' => [40 / (0.8 * 5.670374419e-8 * 0.01) + 300 ** 4],
            'dimensions' => ['variables' => [$kelvinFourth], 'rhs' => [$kelvinFourth],
                'matrix' => [[[0, 0, 0, 0, 0, 0, 0]]]],
            'outputs' => [['name' => 'temperature', 'dimension' => [0, 0, 0, 0, 1, 0, 0],
                'expression' => ['op' => 'pow', 'args' => [['op' => 'var', 'name' => 'r0'],
                    ['op' => 'div', 'args' => [['op' => 'const', 'value' => 1], ['op' => 'const', 'value' => 4]]]]]]]];
    }

    public function test_requested_temperature_is_projected_from_verified_intermediate(): void
    {
        $result = app(ScienceSolverRegistry::class)->solve('linear_system', $this->input());
        $this->assertEqualsWithDelta(557.0334974621942, $result['outputs'][0]['value'], 1e-9);
        $this->assertSame('temperature', $result['outputs'][0]['name']);
        $this->assertSame('declared_dimensions_checked', $result['outputs'][0]['dimension_verification']);
        $this->assertGreaterThan(9e10, $result['solution'][0]);
    }

    public function test_output_cannot_claim_the_wrong_unit(): void
    {
        $input = $this->input();
        $input['outputs'][0]['dimension'] = [0, 1, 0, 0, 0, 0, 0];
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }

    public function test_outputs_require_verified_input_dimensions(): void
    {
        $input = $this->input();
        unset($input['dimensions']);
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }

    public function test_output_cannot_read_an_unavailable_result_slot(): void
    {
        $input = $this->input();
        $input['outputs'][0]['expression'] = ['op' => 'var', 'name' => 'r1'];
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }

    public function test_output_cannot_be_a_retyped_constant_answer(): void
    {
        $input = $this->input();
        $input['outputs'][0]['expression'] = ['op' => 'const', 'value' => 557.0334974621942,
            'dimension' => [0, 0, 0, 0, 1, 0, 0]];
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }

    public function test_cancelled_solver_slot_cannot_disguise_a_constant_answer(): void
    {
        $input = $this->input();
        $input['outputs'][0]['expression'] = ['op' => 'add', 'args' => [
            ['op' => 'sub', 'args' => [
                ['op' => 'pow', 'args' => [['op' => 'var', 'name' => 'r0'], ['op' => 'const', 'value' => 0.25]]],
                ['op' => 'pow', 'args' => [['op' => 'var', 'name' => 'r0'], ['op' => 'const', 'value' => 0.25]]],
            ]],
            ['op' => 'const', 'value' => 557.0334974621942, 'dimension' => [0, 0, 0, 0, 1, 0, 0]],
        ]];
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }

    public function test_failed_solve_never_publishes_a_derived_answer(): void
    {
        $input = $this->input();
        $input['matrix'] = [[0]];
        $result = app(ScienceSolverRegistry::class)->solve('linear_system', $input);
        $this->assertSame('not_solved', $result['status']);
        $this->assertArrayNotHasKey('outputs', $result);
    }

    public function test_integral_output_binds_the_computed_integral_not_the_integrand(): void
    {
        $length = [0, 1, 0, 0, 0, 0, 0];
        $zero = [0, 0, 0, 0, 0, 0, 0];
        $result = app(ScienceSolverRegistry::class)->solve('integrate', [
            'expression' => ['op' => 'const', 'value' => 3], 'lower' => 0, 'upper' => 2,
            'dimensions' => ['x' => $length, 'output' => $length],
            'outputs' => [['name' => 'distance', 'dimension' => $length,
                'expression' => ['op' => 'mul', 'args' => [['op' => 'var', 'name' => 'r0'],
                    ['op' => 'const', 'value' => 2, 'dimension' => $zero]]]]],
        ]);
        $this->assertEqualsWithDelta(12, $result['outputs'][0]['value'], 1e-12);
    }

    public function test_ode_output_binds_final_state_not_initial_state(): void
    {
        $zero = [0, 0, 0, 0, 0, 0, 0];
        $result = app(ScienceSolverRegistry::class)->solve('ode_ivp', [
            'initial' => [0], 'derivatives' => [['op' => 'const', 'value' => 1]],
            't_start' => 0, 't_end' => 2, 'dimensions' => ['t' => $zero, 'states' => [$zero]],
            'outputs' => [['name' => 'square', 'dimension' => $zero,
                'expression' => ['op' => 'pow', 'args' => [['op' => 'var', 'name' => 'r0'], ['op' => 'const', 'value' => 2]]]]],
        ]);
        $this->assertEqualsWithDelta(4, $result['outputs'][0]['value'], 1e-12);
    }
}
