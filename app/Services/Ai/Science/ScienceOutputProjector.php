<?php

namespace App\Services\Ai\Science;

use Illuminate\Validation\ValidationException;

/** Bounded postprocessing of solver slots; never model-retyped candidate answers. */
final class ScienceOutputProjector
{
    public function __construct(private readonly BoundedExpression $expressions, private readonly DimensionVerifier $dimensions) {}

    public function project(string $solver, array $inputs, array $result): array
    {
        if (! array_key_exists('outputs', $inputs)) {
            return $result;
        }
        $outputs = $inputs['outputs'];
        if (! is_array($outputs) || ! array_is_list($outputs) || count($outputs) < 1 || count($outputs) > 8
            || ($result['dimension_verification']['status'] ?? null) !== 'declared_dimensions_checked') {
            $this->invalid('Outputs require a bounded list and checked input dimensions.');
        }
        [$values, $units] = match ($solver) {
            'linear_system' => [$result['solution'] ?? [], $inputs['dimensions']['variables']],
            'integrate', 'root_scalar' => [[$result['value'] ?? null], [$solver === 'root_scalar'
                ? $inputs['dimensions']['x'] : $inputs['dimensions']['output']]],
            'ode_ivp' => [$result['final_state'] ?? [], $inputs['dimensions']['states']],
        };
        $bindings = $dimensions = $names = [];
        foreach ($units as $index => $unit) {
            $dimensions['r'.$index] = ScienceNumbers::dimension($unit);
            if (($result['status'] ?? null) === 'computed') {
                $bindings['r'.$index] = ScienceNumbers::finite($values[$index]);
            }
        }
        $projected = [];
        foreach ($outputs as $output) {
            if (! is_array($output) || ! is_string($output['name'] ?? null)
                || ! preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/', $output['name'])
                || isset($names[$output['name']]) || ! is_array($output['expression'] ?? null)) {
                $this->invalid('Outputs need unique bounded names and arithmetic ASTs.');
            }
            $names[$output['name']] = true;
            $dimension = ScienceNumbers::dimension($output['dimension'] ?? null);
            $expression = $this->expressions->compile($output['expression'], array_keys($dimensions));
            $this->dimensions->verifyExpression($output['expression'], $dimensions, $dimension);
            $this->requireSolverDependency($output['expression'], array_keys($dimensions), $expression, $bindings);
            if (($result['status'] ?? null) === 'computed') {
                $projected[] = ['name' => $output['name'], 'value' => $expression($bindings), 'dimension' => $dimension,
                    'dimension_verification' => 'declared_dimensions_checked'];
            }
        }
        if (($result['status'] ?? null) !== 'computed') {
            return $result;
        }
        $result['outputs'] = $projected;
        $result['output_verification'] = ['status' => 'bounded_arithmetic_and_dependency_checked',
            'limitations' => 'Observed numerical dependency rejects constant/cancelled outputs but cannot prove scientific relevance. Derived outputs do not improve solver accuracy or verify physical formulation.'];

        return $result;
    }

    private function requireSolverDependency(array $node, array $slots, \Closure $expression, array $bindings): void
    {
        $referenced = false;
        $walk = function (array $current) use (&$walk, &$referenced, $slots): void {
            if (($current['op'] ?? null) === 'var' && in_array($current['name'] ?? null, $slots, true)) {
                $referenced = true;
            }
            foreach ($current['args'] ?? [] as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($node);
        if (! $referenced) {
            $this->invalid('Every output must depend on a solver result slot.');
        }
        if ($bindings === []) {
            return; // Failed solves publish nothing; structural validation still applies.
        }
        $baseline = $expression($bindings);
        foreach ($bindings as $name => $value) {
            $delta = max(abs($value) * 1e-6, 1e-9);
            foreach ([1, -1] as $sign) {
                $perturbed = $bindings;
                $perturbed[$name] = $value + $sign * $delta;
                if (! is_finite($perturbed[$name]) || $perturbed[$name] === $value) {
                    continue;
                }
                try {
                    if ($expression($perturbed) !== $baseline) {
                        return;
                    }
                } catch (ValidationException) {
                    // Try the opposite direction or another result slot.
                }
            }
        }
        $this->invalid('Output is numerically independent of the solver result.');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['outputs' => $message]);
    }
}
