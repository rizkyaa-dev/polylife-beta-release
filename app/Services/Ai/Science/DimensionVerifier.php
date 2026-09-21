<?php

namespace App\Services\Ai\Science;

use Illuminate\Validation\ValidationException;

/** Checks declared SI dimensions; it cannot establish that the physical model is true. */
final class DimensionVerifier
{
    public const ORDER = ['mass', 'length', 'time', 'current', 'temperature', 'amount', 'luminous_intensity'];

    public function __construct(private readonly BoundedExpression $expressions) {}

    public function verify(string $solver, array $inputs): array
    {
        if (! array_key_exists('dimensions', $inputs)) {
            return ['status' => 'unverified', 'reason' => 'No structured dimensional declarations supplied.'];
        }
        $d = $inputs['dimensions'];
        if (! is_array($d)) {
            $this->invalid('Dimension declarations must be an object.');
        }
        if ($solver === 'integrate') {
            $variables = ['x' => $this->vector($d['x'] ?? null)];
            $this->expressions->compile($inputs['expression'] ?? []);
            $actual = $this->combine($this->expression($inputs['expression'], $variables), $variables['x'], 1);
            $this->equal($actual, $this->vector($d['output'] ?? null));
        } elseif ($solver === 'root_scalar') {
            $variables = ['x' => $this->vector($d['x'] ?? null)];
            $this->expressions->compile($inputs['expression'] ?? []);
            $this->equal($this->expression($inputs['expression'], $variables), $this->vector($d['output'] ?? null));
        } elseif ($solver === 'ode_ivp') {
            if (! is_array($inputs['initial'] ?? null) || ! is_array($inputs['derivatives'] ?? null)
                || ! is_array($d['states'] ?? null) || count($d['states']) !== count($inputs['initial'])
                || count($inputs['derivatives']) !== count($inputs['initial'])) {
                $this->invalid('State dimension declaration sizes do not match.');
            }
            $variables = ['t' => $this->vector($d['t'] ?? null)];
            foreach ($inputs['initial'] ?? [] as $i => $value) {
                $variables['y'.$i] = $this->vector($d['states'][$i] ?? null);
            }
            foreach ($inputs['derivatives'] ?? [] as $i => $expression) {
                $this->expressions->compile($expression, array_keys($variables));
                $actual = $this->expression($expression, $variables);
                $expected = $this->combine($variables['y'.$i] ?? $this->invalid('Missing state dimension.'), $variables['t'], -1);
                $this->equal($actual, $expected);
            }
        } elseif ($solver === 'linear_system') {
            $matrix = $inputs['matrix'] ?? [];
            if (! is_array($matrix) || ! is_array($d['variables'] ?? null) || ! is_array($d['rhs'] ?? null) || ! is_array($d['matrix'] ?? null)
                || count($d['variables']) !== count($matrix) || count($d['rhs']) !== count($matrix)
                || count($d['matrix'] ?? []) !== count($matrix)) {
                $this->invalid('Linear dimension declaration sizes do not match.');
            }
            foreach ($matrix as $i => $row) {
                $rhs = $this->vector($d['rhs'][$i] ?? null);
                if (! is_array($row) || ! is_array($d['matrix'][$i] ?? null) || count($d['matrix'][$i]) !== count($row)) {
                    $this->invalid('Missing coefficient dimensions.');
                }
                foreach ($row as $j => $coefficient) {
                    $actual = $this->combine($this->vector($d['matrix'][$i][$j] ?? null), $this->vector($d['variables'][$j] ?? null), 1);
                    $this->equal($actual, $rhs);
                }
            }
        } else {
            return ['status' => 'unverified', 'reason' => 'No dimensional verifier for this solver.'];
        }

        return ['status' => 'declared_dimensions_checked', 'basis' => self::ORDER,
            'limitations' => 'Checks consistency of declared dimensions, not measured units, scale conversions or physical truth.'];
    }

    public function verifyExpression(array $node, array $variables, array $expected): void
    {
        $this->expressions->compile($node, array_keys($variables));
        $this->equal($this->expression($node, $variables), $this->vector($expected));
    }

    private function expression(array $node, array $variables): array
    {
        if ($node['op'] === 'const') {
            return array_key_exists('dimension', $node) ? $this->vector($node['dimension']) : array_fill(0, 7, 0.0);
        }
        if ($node['op'] === 'var') {
            return $variables[$node['name']] ?? $this->invalid('Missing variable dimension.');
        }
        $args = $node['args'];
        $a = $this->expression($args[0], $variables);
        $b = isset($args[1]) ? $this->expression($args[1], $variables) : null;
        switch ($node['op']) {
            case 'add':
            case 'sub':
                $this->equal($a, $b);

                return $a;
            case 'mul': return $this->combine($a, $b, 1);
            case 'div': return $this->combine($a, $b, -1);
            case 'neg': return $a;
            case 'sqrt': return $this->vector(array_map(fn ($value) => $value / 2, $a));
            case 'pow':
                $zero = array_fill(0, 7, 0.0);
                $this->equal($b, $zero);
                if ($a === $zero) {
                    return $zero;
                }
                // Fold bounded constant expressions (e.g. 1/4), never variables.
                $exponent = $this->expressions->compile($args[1], [])([]);

                return $this->vector(array_map(fn ($value) => $value * $exponent, $a));
            default:
                $this->equal($a, array_fill(0, 7, 0.0));

                return array_fill(0, 7, 0.0);
        }
    }

    private function vector(mixed $value): array
    {
        return ScienceNumbers::dimension($value);
    }

    private function combine(array $a, array $b, int $sign): array
    {
        return $this->vector(array_map(fn ($x, $y) => $x + $sign * $y, $a, $b));
    }

    private function equal(array $a, array $b): void
    {
        foreach ($a as $i => $value) {
            if (abs($value - $b[$i]) > 1e-9) {
                $this->invalid('Inconsistent dimensions in the proposed model.');
            }
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['dimensions' => $message]);
    }
}
