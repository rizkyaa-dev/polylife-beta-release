<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\LinearSystemSolver;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceSolverTest extends TestCase
{
    public function test_pivoting_and_residual_check(): void
    {
        $result = (new LinearSystemSolver)->solve(['matrix' => [[0, 2, 1], [1, -2, -3], [2, 3, 1]], 'rhs' => [3, 0, 7]]);
        $this->assertSame('numerically_checked', $result['verification']['status']);
        $this->assertEqualsWithDelta(1, $result['solution'][0], 1e-10);
        $this->assertEqualsWithDelta(2, $result['solution'][1], 1e-10);
        $this->assertEqualsWithDelta(-1, $result['solution'][2], 1e-10);
    }

    public function test_singular_problem_does_not_claim_success(): void
    {
        $result = (new LinearSystemSolver)->solve(['matrix' => [[1, 2], [2, 4]], 'rhs' => [3, 6]]);
        $this->assertSame('not_solved', $result['status']);
        $this->assertArrayNotHasKey('solution', $result);
    }

    public function test_dimension_mismatch_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new LinearSystemSolver)->solve(['matrix' => [[1, 2], [3]], 'rhs' => [1, 2]]);
    }

    public function test_resource_limit_is_enforced(): void
    {
        $this->expectException(ValidationException::class);
        (new LinearSystemSolver)->solve(['matrix' => array_fill(0, 33, [1]), 'rhs' => [1]]);
    }

    public function test_non_finite_values_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new LinearSystemSolver)->solve(['matrix' => [[INF]], 'rhs' => [1]]);
    }

    public function test_scaled_equations_preserve_solution(): void
    {
        $result = (new LinearSystemSolver)->solve(['matrix' => [[1e-100, 2e-100], [3e100, 4e100]], 'rhs' => [5e-100, 11e100]]);
        $this->assertEqualsWithDelta(1, $result['solution'][0], 1e-10);
        $this->assertEqualsWithDelta(2, $result['solution'][1], 1e-10);
    }

    public function test_verification_overflow_does_not_become_zero_error(): void
    {
        $this->expectException(ValidationException::class);
        (new LinearSystemSolver)->solve(['matrix' => [[1e308, 1e308], [1e308, -1e308]], 'rhs' => [1, 1]]);
    }

    public function test_subnormal_residual_is_not_divided_by_normal_float_minimum(): void
    {
        $matrix = [[1e-320, 2e-320], [3e-320, 4e-320]];
        $rhs = [5e-320, 11e-320];
        $result = (new LinearSystemSolver)->solve(['matrix' => $matrix, 'rhs' => $rhs]);
        $x = $result['solution'];
        $residual = max(abs($matrix[0][0] * $x[0] + $matrix[0][1] * $x[1] - $rhs[0]),
            abs($matrix[1][0] * $x[0] + $matrix[1][1] * $x[1] - $rhs[1]));
        $this->assertGreaterThan(0, $residual);
        $expected = $residual / ((abs($matrix[1][0]) + abs($matrix[1][1])) * max(array_map('abs', $x)) + max($rhs));
        $this->assertSame($expected, $result['verification']['relative_backward_error']);
    }
}
