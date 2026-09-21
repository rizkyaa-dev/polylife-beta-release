<?php

namespace App\Services\Ai\Science;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class LinearSystemSolver implements ScienceSolver
{
    public function name(): string
    {
        return 'linear_system';
    }

    public function specification(): array
    {
        return ['name' => $this->name(), 'inputs' => ['matrix' => 'Square finite numeric matrix, dimension 1..32', 'rhs' => 'Finite numeric vector of matching dimension'],
            'method' => 'Gaussian elimination with scaled partial pivoting', 'limits' => ['dimension' => 32],
            'units' => 'Coefficients must be normalized to consistent units by the planner; numerical checks do not verify the physical model.'];
    }

    public function solve(array $inputs): array
    {
        Validator::make($inputs, [
            'matrix' => ['required', 'array', 'min:1', 'max:32'],
            'matrix.*' => ['required', 'array', 'min:1', 'max:32'],
            'matrix.*.*' => ['required', 'numeric'],
            'rhs' => ['required', 'array', 'min:1', 'max:32'], 'rhs.*' => ['required', 'numeric'],
        ])->validate();
        ScienceNumbers::list($inputs['matrix']);
        ScienceNumbers::list($inputs['rhs']);
        $a = array_values($inputs['matrix']);
        $b = array_values($inputs['rhs']);
        $n = count($a);
        if (count($b) !== $n) {
            throw ValidationException::withMessages(['rhs' => 'Vector dimension does not match the matrix.']);
        }
        foreach ($a as &$row) {
            ScienceNumbers::list($row);
            $row = array_values($row);
            if (count($row) !== $n) {
                throw ValidationException::withMessages(['matrix' => 'Matrix must be square.']);
            }
            $row = array_map($this->finite(...), $row);
        }
        unset($row);
        $b = array_map($this->finite(...), $b);
        $original = $a;
        $rhs = $b;
        $scales = array_map(fn ($row) => max(array_map('abs', $row)), $a);
        for ($k = 0; $k < $n; $k++) {
            $pivot = $k;
            $ratio = -1;
            for ($i = $k; $i < $n; $i++) {
                $candidate = $scales[$i] == 0 ? 0 : abs($a[$i][$k]) / $scales[$i];
                if ($candidate > $ratio) {
                    $ratio = $candidate;
                    $pivot = $i;
                }
            }
            if ($ratio <= 1e-14) {
                return ['status' => 'not_solved', 'reason' => 'singular_or_numerically_ill_conditioned', 'verification' => ['status' => 'unverified']];
            }
            [$a[$k], $a[$pivot]] = [$a[$pivot], $a[$k]];
            [$b[$k], $b[$pivot]] = [$b[$pivot], $b[$k]];
            [$scales[$k], $scales[$pivot]] = [$scales[$pivot], $scales[$k]];
            for ($i = $k + 1; $i < $n; $i++) {
                $factor = $this->finite($a[$i][$k] / $a[$k][$k]);
                for ($j = $k; $j < $n; $j++) {
                    $a[$i][$j] = $this->finite($a[$i][$j] - $factor * $a[$k][$j]);
                }
                $b[$i] = $this->finite($b[$i] - $factor * $b[$k]);
            }
        }
        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = $b[$i];
            for ($j = $i + 1; $j < $n; $j++) {
                $sum -= $a[$i][$j] * $x[$j];
            }
            $x[$i] = $this->finite($sum / $a[$i][$i]);
        }
        $residual = 0.0;
        $normA = 0.0;
        foreach ($original as $i => $row) {
            $sum = 0.0;
            foreach ($row as $j => $coefficient) {
                $sum += $coefficient * $x[$j];
            }
            $residual = max($residual, abs($this->finite($sum - $rhs[$i])));
            $normA = max($normA, $this->finite(array_sum(array_map('abs', $row))));
        }
        $denominator = $this->finite($normA * max(array_map('abs', $x)) + max(array_map('abs', $rhs)));
        $error = $denominator === 0.0 ? 0.0 : $this->finite($residual / $denominator);

        return ['status' => 'computed', 'solution' => $x, 'verification' => [
            'status' => $error <= 1e-10 ? 'numerically_checked' : 'failed',
            'relative_backward_error' => $error, 'tolerance' => 1e-10,
            'limitations' => 'Small residual does not prove model correctness or forward accuracy for ill-conditioned systems.',
        ]];
    }

    private function finite(mixed $value): float
    {
        $number = ScienceNumbers::finite($value);
        if (! is_finite($number)) {
            throw ValidationException::withMessages(['inputs' => 'Non-finite input or arithmetic overflow.']);
        }

        return $number;
    }
}
