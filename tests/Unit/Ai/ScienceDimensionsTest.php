<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\DimensionVerifier;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceDimensionsTest extends TestCase
{
    public function test_structural_preflight_rejects_malformed_shapes_without_type_errors(): void
    {
        $zero = [0, 0, 0, 0, 0, 0, 0];
        foreach ([
            ['ode_ivp', ['initial' => 'bad', 'derivatives' => [], 'dimensions' => ['t' => $zero, 'states' => []]]],
            ['ode_ivp', ['initial' => [0], 'derivatives' => 'bad', 'dimensions' => ['t' => $zero, 'states' => [$zero]]]],
            ['linear_system', ['matrix' => 'bad', 'dimensions' => ['variables' => [], 'rhs' => [], 'matrix' => []]]],
            ['linear_system', ['matrix' => ['bad'], 'dimensions' => ['variables' => [$zero], 'rhs' => [$zero], 'matrix' => [[$zero]]]]],
        ] as [$solver, $inputs]) {
            try {
                app(DimensionVerifier::class)->verify($solver, $inputs);
                $this->fail('Malformed scientific shape was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function workInputs(bool $wrongCoefficient = false): array
    {
        return ['expression' => ['op' => 'mul', 'args' => [
            ['op' => 'const', 'value' => 10, 'dimension' => [1, $wrongCoefficient ? -1 : 0, -2, 0, 0, 0, 0]],
            ['op' => 'var', 'name' => 'x'],
        ]], 'dimensions' => ['x' => [0, 1, 0, 0, 0, 0, 0], 'output' => [1, 2, -2, 0, 0, 0, 0]]];
    }

    public function test_force_work_dimensions_are_checked(): void
    {
        $result = app(DimensionVerifier::class)->verify('integrate', $this->workInputs());
        $this->assertSame('declared_dimensions_checked', $result['status']);
    }

    public function test_wrong_coefficient_units_from_live_probe_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(DimensionVerifier::class)->verify('integrate', $this->workInputs(true));
    }

    public function test_exponential_requires_dimensionless_argument(): void
    {
        $this->expectException(ValidationException::class);
        app(DimensionVerifier::class)->verify('integrate', ['expression' => ['op' => 'exp', 'args' => [['op' => 'var', 'name' => 'x']]],
            'dimensions' => ['x' => [0, 1, 0, 0, 0, 0, 0], 'output' => [0, 1, 0, 0, 0, 0, 0]]]);
    }

    public function test_omitted_dimensions_are_explicitly_unverified(): void
    {
        $this->assertSame('unverified', app(DimensionVerifier::class)->verify('integrate', [])['status']);
    }

    public function test_constant_rational_exponents_preserve_dimensional_roots(): void
    {
        $result = app(DimensionVerifier::class)->verify('integrate', ['expression' => ['op' => 'pow', 'args' => [
            ['op' => 'const', 'value' => 16, 'dimension' => [0, 0, 0, 0, 4, 0, 0]],
            ['op' => 'div', 'args' => [['op' => 'const', 'value' => 1], ['op' => 'const', 'value' => 4]]],
        ]], 'dimensions' => ['x' => [0, 0, 0, 0, 0, 0, 0], 'output' => [0, 0, 0, 0, 1, 0, 0]]]);
        $this->assertSame('declared_dimensions_checked', $result['status']);
    }

    public function test_variable_exponents_on_dimensional_bases_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(DimensionVerifier::class)->verify('integrate', ['expression' => ['op' => 'pow', 'args' => [
            ['op' => 'const', 'value' => 16, 'dimension' => [0, 0, 0, 0, 4, 0, 0]],
            ['op' => 'var', 'name' => 'x'],
        ]], 'dimensions' => ['x' => [0, 0, 0, 0, 0, 0, 0], 'output' => [0, 0, 0, 0, 1, 0, 0]]]);
    }
}
