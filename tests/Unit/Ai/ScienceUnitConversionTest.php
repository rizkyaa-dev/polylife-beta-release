<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\ScienceSolverRegistry;
use App\Services\Ai\Science\ScienceUnitConverter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceUnitConversionTest extends TestCase
{
    public function test_correct_tiny_conversion_still_passes(): void
    {
        $verification = app(ScienceUnitConverter::class)->verify('integrate', [
            'expression' => ['op' => 'const', 'value' => 1e-18, 'dimension' => [-1, -2, 4, 2, 0, 0, 0]],
            'dimensions' => ['x' => [0, 0, 0, 0, 0, 0, 0]],
            'conversions' => [['path' => ['expression', 'value'], 'source_value' => 1e-12, 'source_unit' => 'uF']],
        ]);
        $this->assertSame('declared_conversions_checked', $verification['status']);
    }

    public function test_tiny_conversion_cannot_accept_zero_or_a_wrong_scale(): void
    {
        foreach ([[0.0, 1e-12], [1e-15, 1e-12], [0.0, 1e-320]] as [$value, $source]) {
            try {
                app(ScienceUnitConverter::class)->verify('integrate', [
                    'expression' => ['op' => 'const', 'value' => $value, 'dimension' => [-1, -2, 4, 2, 0, 0, 0]],
                    'dimensions' => ['x' => [0, 0, 0, 0, 0, 0, 0]],
                    'conversions' => [['path' => ['expression', 'value'], 'source_value' => $source, 'source_unit' => 'uF']],
                ]);
                $this->fail('Incorrect tiny conversion was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function circuit(): array
    {
        return ['matrix' => [[2000]], 'rhs' => [10],
            'dimensions' => ['variables' => [[0, 0, 0, 1, 0, 0, 0]],
                'rhs' => [[1, 2, -3, -1, 0, 0, 0]],
                'matrix' => [[[1, 2, -3, -2, 0, 0, 0]]]],
            'conversions' => [['path' => ['matrix', 0, 0], 'source_value' => 2, 'source_unit' => 'kohm']]];
    }

    public function test_conversion_is_bound_to_the_actual_solver_value_and_dimension(): void
    {
        $result = app(ScienceSolverRegistry::class)->solve('linear_system', $this->circuit());
        $this->assertEqualsWithDelta(0.005, $result['solution'][0], 1e-14);
        $this->assertSame('declared_conversions_checked', $result['conversion_verification']['status']);
        $this->assertSame(2000.0, $result['conversion_verification']['items'][0]['si_value']);
        $this->assertSame('ohm', $result['conversion_verification']['items'][0]['si_unit']);
    }

    public function test_incorrect_factor_cannot_be_laundered_as_si(): void
    {
        $input = $this->circuit();
        $input['matrix'][0][0] = 2;
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }

    public function test_source_unit_must_match_the_target_dimension(): void
    {
        $input = $this->circuit();
        $input['conversions'][0]['source_unit'] = 'cm';
        $input['matrix'][0][0] = 0.02;
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }

    public function test_affine_celsius_conversion_is_checked_on_an_ode_state(): void
    {
        $zero = [0, 0, 0, 0, 0, 0, 0];
        $temperature = [0, 0, 0, 0, 1, 0, 0];
        $input = ['initial' => [293.15], 'derivatives' => [['op' => 'const', 'value' => 0,
            'dimension' => [0, 0, -1, 0, 1, 0, 0]]], 't_start' => 0, 't_end' => 1,
            'dimensions' => ['t' => [0, 0, 1, 0, 0, 0, 0], 'states' => [$temperature]],
            'conversions' => [['path' => ['initial', 0], 'source_value' => 20, 'source_unit' => 'degC']]];
        $result = app(ScienceSolverRegistry::class)->solve('ode_ivp', $input);
        $this->assertEqualsWithDelta(293.15, $result['final_state'][0], 1e-12);
        $this->assertSame('K', $result['conversion_verification']['items'][0]['si_unit']);
    }

    public function test_conversion_path_cannot_escape_supported_numeric_fields(): void
    {
        $input = $this->circuit();
        $input['conversions'][0]['path'] = ['outputs', 0, 'name'];
        $this->expectException(ValidationException::class);
        app(ScienceSolverRegistry::class)->solve('linear_system', $input);
    }
}
