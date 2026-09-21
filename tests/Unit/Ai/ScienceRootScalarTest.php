<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceRootScalarTest extends TestCase
{
    public function test_off_grid_pole_is_not_published_as_a_root(): void
    {
        $result = $this->solve(['expression' => ['op' => 'div', 'args' => [
            ['op' => 'const', 'value' => 1], ['op' => 'sub', 'args' => [
                ['op' => 'var', 'name' => 'x'], ['op' => 'const', 'value' => 0.123456789],
            ]],
        ]], 'lower' => 0, 'upper' => 1, 'tolerance' => 1e-8]);
        $this->assertSame('not_converged', $result['status']);
        $this->assertNull($result['value']);
    }

    private function solve(array $inputs): array
    {
        return app(ScienceSolverRegistry::class)->solve('root_scalar', $inputs);
    }

    public function test_bracketed_root_has_bounded_x_error_and_checked_dimensions(): void
    {
        $result = $this->solve(['expression' => ['op' => 'sub', 'args' => [
            ['op' => 'pow', 'args' => [['op' => 'var', 'name' => 'x'], ['op' => 'const', 'value' => 2]]],
            ['op' => 'const', 'value' => 2],
        ]], 'lower' => 1, 'upper' => 2, 'tolerance' => 1e-10,
            'dimensions' => ['x' => [0, 0, 0, 0, 0, 0, 0], 'output' => [0, 0, 0, 0, 0, 0, 0]]]);

        $this->assertSame('computed', $result['status']);
        $this->assertEqualsWithDelta(sqrt(2), $result['value'], 1e-10);
        $this->assertLessThanOrEqual(2e-10, $result['verification']['absolute_bracket_width']);
        $this->assertSame('declared_dimensions_checked', $result['dimension_verification']['status']);
    }

    public function test_endpoint_root_is_accepted_without_iterations(): void
    {
        $result = $this->solve(['expression' => ['op' => 'var', 'name' => 'x'], 'lower' => 0, 'upper' => 2]);
        $this->assertSame(0.0, $result['value']);
        $this->assertSame(0, $result['iterations']);
    }

    public function test_equal_endpoint_signs_do_not_publish_a_candidate(): void
    {
        $result = $this->solve(['expression' => ['op' => 'add', 'args' => [
            ['op' => 'pow', 'args' => [['op' => 'var', 'name' => 'x'], ['op' => 'const', 'value' => 2]]],
            ['op' => 'const', 'value' => 1],
        ]], 'lower' => -2, 'upper' => 2]);
        $this->assertSame('not_solved', $result['status']);
        $this->assertNull($result['value']);
    }

    public function test_invalid_bracket_and_inconsistent_dimensions_are_rejected(): void
    {
        foreach ([
            ['expression' => ['op' => 'var', 'name' => 'x'], 'lower' => 2, 'upper' => 1],
            ['expression' => ['op' => 'var', 'name' => 'x'], 'lower' => -1, 'upper' => 1,
                'dimensions' => ['x' => [0, 1, 0, 0, 0, 0, 0], 'output' => [0, 0, 1, 0, 0, 0, 0]]],
        ] as $input) {
            try {
                $this->solve($input);
                $this->fail('Invalid root contract was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_singular_midpoint_is_rejected_instead_of_being_reported_as_a_root(): void
    {
        $this->expectException(ValidationException::class);
        $this->solve(['expression' => ['op' => 'div', 'args' => [
            ['op' => 'const', 'value' => 1], ['op' => 'var', 'name' => 'x'],
        ]], 'lower' => -1, 'upper' => 1]);
    }
}
