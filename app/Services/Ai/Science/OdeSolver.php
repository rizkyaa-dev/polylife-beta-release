<?php

namespace App\Services\Ai\Science;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OdeSolver implements ScienceSolver
{
    public function __construct(private readonly BoundedExpression $expressions) {}

    public function name(): string
    {
        return 'ode_ivp';
    }

    public function specification(): array
    {
        return ['name' => $this->name(), 'method' => 'Adaptive RK4 with step doubling',
            'inputs' => ['derivatives' => 'Array of 1..4 arithmetic ASTs for dy0/dt,dy1/dt,...; same grammar as integrate, variables t,y0,y1,y2,y3 as applicable.',
                'initial' => 'Finite numeric array matching derivatives',
                't_start' => 'Finite independent-parameter start; t is a field name, not necessarily physical time. Declare its actual SI dimensions.',
                't_end' => 'Finite independent-parameter end greater than t_start',
                'tolerance' => 'Absolute componentwise local error target in each state SI unit, 1e-8..1e-2; default 1e-6. Not dimensionless; no certified global bound.'],
            'limits' => ['attempts' => 2048, 'dimension' => 4, 'output_samples' => 65],
            'limitations' => 'Nonstiff smooth initial value problems only; no discontinuities, event detection, boundary-value or stiff systems. Local step estimates are not certified global error.'];
    }

    public function solve(array $inputs): array
    {
        Validator::make($inputs, ['initial' => ['required', 'array', 'min:1', 'max:4'], 'initial.*' => ['required', 'numeric'],
            'derivatives' => ['required', 'array', 'min:1', 'max:4'], 'derivatives.*' => ['required', 'array'],
            't_start' => ['required', 'numeric'], 't_end' => ['required', 'numeric'],
            'tolerance' => ['sometimes', 'numeric', 'between:0.00000001,0.01']])->validate();
        ScienceNumbers::list($inputs['initial']);
        ScienceNumbers::list($inputs['derivatives']);
        $y = array_map($this->finite(...), array_values($inputs['initial']));
        $expressions = array_values($inputs['derivatives']);
        if (count($y) !== count($expressions)) {
            $this->invalid('Derivative and state dimensions do not match.');
        }
        $variables = ['t', ...array_map(fn ($i) => 'y'.$i, array_keys($y))];
        $derivatives = array_map(fn ($expression) => $this->expressions->compile($expression, $variables), $expressions);
        $t = $this->finite($inputs['t_start']);
        $end = $this->finite($inputs['t_end']);
        $span = $this->finite($end - $t);
        $start = $t;
        $tolerance = ScienceNumbers::finite($inputs['tolerance'] ?? 1e-6);
        if ($span <= 0) {
            $this->invalid('Time interval must be positive.');
        }
        $step = $span / 32;
        $samples = [['t' => $t, 'state' => $y]];
        $sampleIndex = 1;
        $nextSample = min($end, $start + $span * $sampleIndex / 64);
        $accepted = 0;
        $maxError = 0.0;
        $attempts = 0;
        while ($t < $end && $attempts < 2048) {
            $attempts++;
            $h = min($step, $end - $t, $nextSample - $t);
            if ($h <= 0 || $t + $h === $t) {
                return $this->failure($attempts, 'time_step_underflow');
            }
            $whole = $this->rk4($derivatives, $t, $y, $h);
            $half = $this->rk4($derivatives, $t, $y, $h / 2);
            $fine = $this->rk4($derivatives, $t + $h / 2, $half, $h / 2);
            $error = max(array_map(fn ($a, $b) => abs($a - $b) / 15, $fine, $whole));
            $this->finite($error);
            if ($error <= $tolerance) {
                $y = array_map(fn ($a, $b) => $this->finite($a + ($a - $b) / 15), $fine, $whole);
                $t = $h === $nextSample - $t ? $nextSample : $t + $h;
                $accepted++;
                $maxError = max($maxError, $error);
                if ($t >= $nextSample) {
                    $samples[] = ['t' => $t, 'state' => $y];
                    $sampleIndex++;
                    $nextSample = $sampleIndex >= 64 ? $end : min($end, $start + $span * $sampleIndex / 64);
                }
            }
            $factor = $error == 0 ? 2 : min(2, max(0.2, 0.9 * ($tolerance / $error) ** 0.2));
            $step = $this->finite($h * $factor);
        }
        if ($t < $end) {
            return $this->failure($attempts, 'iteration_budget_exceeded');
        }

        return ['status' => 'computed', 'final_state' => $y, 'samples' => array_slice($samples, 0, 65),
            'attempts' => $attempts, 'accepted_steps' => $accepted, 'verification' => [
                'status' => 'estimated_local_error_only', 'max_estimated_local_error' => $maxError,
                'tolerance' => $tolerance, 'limitations' => 'Local estimates do not certify global accuracy, stability or physical correctness.',
                'tolerance_interpretation' => 'Absolute componentwise target in each state SI unit; not dimensionless or a global bound.',
            ]];
    }

    private function rk4(array $f, float $t, array $y, float $h): array
    {
        $evaluate = function (float $time, array $state) use ($f): array {
            $values = ['t' => $time];
            foreach ($state as $i => $value) {
                $values['y'.$i] = $value;
            }

            return array_map(fn ($derivative) => $derivative($values), $f);
        };
        $advance = fn ($slope, $scale) => array_map(fn ($a, $b) => $this->finite($a + $scale * $h * $b), $y, $slope);
        $k1 = $evaluate($t, $y);
        $k2 = $evaluate($t + $h / 2, $advance($k1, 0.5));
        $k3 = $evaluate($t + $h / 2, $advance($k2, 0.5));
        $k4 = $evaluate($t + $h, $advance($k3, 1));

        return array_map(fn ($a, $b, $c, $d, $e) => $this->finite($a + $h / 6 * ($b + 2 * $c + 2 * $d + $e)), $y, $k1, $k2, $k3, $k4);
    }

    private function finite(mixed $value): float
    {
        $number = ScienceNumbers::finite($value);
        if (! is_finite($number)) {
            $this->invalid('Non-finite ODE value or arithmetic overflow.');
        }

        return $number;
    }

    private function failure(int $attempts, string $reason): array
    {
        return ['status' => 'not_converged', 'reason' => $reason, 'attempts' => min($attempts, 2048),
            'final_state' => null, 'samples' => [], 'verification' => ['status' => 'unverified']];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['ode' => $message]);
    }
}
