<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\BoundedExpression;
use App\Services\Ai\Science\IntegrationSolver;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceIntegrationTest extends TestCase
{
    public function test_dyadic_oscillation_alias_does_not_return_one_with_zero_error(): void
    {
        foreach ([8, 12, 24, 64] as $frequency) {
            $result = app(IntegrationSolver::class)->solve(['expression' => ['op' => 'cos', 'args' => [
                ['op' => 'mul', 'args' => [['op' => 'const', 'value' => $frequency * M_PI], ['op' => 'var', 'name' => 'x']]],
            ]], 'lower' => 0, 'upper' => 1, 'tolerance' => 1e-8]);
            $this->assertSame('computed', $result['status']);
            $this->assertEqualsWithDelta(0, $result['value'], 1e-8);
        }
    }

    public function test_polynomial_and_reversed_bounds_match_analytic_integral(): void
    {
        $expression = ['op' => 'pow', 'args' => [['op' => 'var', 'name' => 'x'], ['op' => 'const', 'value' => 2]]];
        foreach ([[0, 3, 9], [3, 0, -9]] as [$lower, $upper, $expected]) {
            $result = app(IntegrationSolver::class)->solve(compact('expression', 'lower', 'upper'));
            $this->assertSame('computed', $result['status']);
            $this->assertEqualsWithDelta($expected, $result['value'], 1e-7);
            $this->assertSame('estimated_error_only', $result['verification']['status']);
        }
    }

    public function test_sine_matches_analytic_integral(): void
    {
        $result = app(IntegrationSolver::class)->solve(['expression' => ['op' => 'sin', 'args' => [['op' => 'var', 'name' => 'x']]], 'lower' => 0, 'upper' => M_PI]);
        $this->assertEqualsWithDelta(2, $result['value'], 1e-7);
        $this->assertLessThanOrEqual(8193, $result['evaluations']);
    }

    public function test_singular_integrand_is_not_published_as_verified(): void
    {
        $this->expectException(ValidationException::class);
        app(IntegrationSolver::class)->solve(['expression' => ['op' => 'div', 'args' => [['op' => 'const', 'value' => 1], ['op' => 'var', 'name' => 'x']]], 'lower' => -1, 'upper' => 1]);
    }

    public function test_arbitrary_function_names_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(BoundedExpression::class)->compile(['op' => 'system', 'args' => []]);
    }

    public function test_deep_expression_is_bounded(): void
    {
        $expression = ['op' => 'const', 'value' => 1];
        for ($i = 0; $i < 20; $i++) {
            $expression = ['op' => 'neg', 'args' => [$expression]];
        }
        $this->expectException(ValidationException::class);
        app(BoundedExpression::class)->compile($expression);
    }

    public function test_representable_constant_integrals_do_not_underflow_or_overflow_intermediate_weights(): void
    {
        foreach ([[1e-323, 1e308], [1e308, 5e-324]] as [$upper, $value]) {
            $result = app(IntegrationSolver::class)->solve(['expression' => ['op' => 'const', 'value' => $value],
                'lower' => 0, 'upper' => $upper]);
            $this->assertSame('computed', $result['status']);
            $this->assertGreaterThan(0, $result['value']);
            $this->assertEqualsWithDelta($upper * $value, $result['value'], 1e-28);
        }
    }
}
