<?php

namespace App\Services\Ai\Science;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class IntegrationSolver implements ScienceSolver
{
    public function __construct(private readonly BoundedExpression $expressions) {}

    public function name(): string
    {
        return 'integrate';
    }

    public function specification(): array
    {
        return ['name' => $this->name(), 'method' => 'Bounded adaptive Simpson quadrature with two unequal initial panels and an original-midpoint domain check',
            'inputs' => ['expression' => 'Arithmetic AST: {op:const,value:number}, {op:var,name:x}, or {op:add|sub|mul|div|pow,args:[AST,AST]}, {op:neg|sin|cos|exp|log|sqrt,args:[AST]}. Max 128 nodes, depth 12; no code or strings.',
                'lower' => 'Finite number', 'upper' => 'Finite number', 'tolerance' => 'Optional absolute error target 1e-10..1e-2 (default 1e-7)'],
            'limits' => ['evaluations' => 8193, 'subdivision_depth' => 20],
            'limitations' => 'Only smooth nonsingular real integrands on finite bounds. Unequal seeds mitigate dyadic aliasing but cannot certify oscillatory, discontinuous or singular integrands. Reports evaluations and estimated absolute error, not a certified bound or minimum node spacing.'];
    }

    public function solve(array $inputs): array
    {
        Validator::make($inputs, ['expression' => ['required', 'array'], 'lower' => ['required', 'numeric'],
            'upper' => ['required', 'numeric'], 'tolerance' => ['sometimes', 'numeric', 'between:0.0000000001,0.01']])->validate();
        $a = ScienceNumbers::finite($inputs['lower']);
        $b = ScienceNumbers::finite($inputs['upper']);
        $tol = ScienceNumbers::finite($inputs['tolerance'] ?? 1e-7);
        if (! is_finite($a) || ! is_finite($b) || ! is_finite($b - $a)) {
            throw ValidationException::withMessages(['bounds' => 'Integration bounds must be finite and representable.']);
        }
        $f = $this->expressions->compile($inputs['expression']);
        if ($a === $b) {
            return $this->result(0, 0, 0, true);
        }
        $sign = $a < $b ? 1 : -1;
        if ($sign < 0) {
            [$a, $b] = [$b, $a];
        }
        $evaluations = 0;
        $evaluate = function (float $x) use ($f, &$evaluations): float {
            $evaluations++;

            return $f(['x' => $x]);
        };
        $fa = $evaluate($a);
        // Retain the original midpoint domain check as well as the unequal seeds.
        $fm = $evaluate($a + ($b - $a) / 2);
        $fb = $evaluate($b);
        $simpson = function ($left, $right, $fl, $fc, $fr): float {
            $scale = max(abs($fl), abs($fc), abs($fr));
            if ($scale === 0.0) {
                return 0.0;
            }
            // Normalize first: raw weights overflow for large f, while dividing
            // a subnormal interval by six can erase a representable integral.
            $average = ($fl / $scale + 4 * ($fc / $scale) + $fr / $scale) / 6;
            $product = ($right - $left) * $scale;

            return is_finite($product) ? $product * $average : ($right - $left) * ($scale * $average);
        };
        // Unequal seeds break the reproduced dyadic harmonic alias. Finite
        // sampling still cannot certify arbitrary oscillations or narrow peaks.
        $cut = $a + ($b - $a) * 0.3819660112501051;
        if ($cut > $a && $cut < $b) {
            $fc = $evaluate($cut);
            $flm = $evaluate($a + ($cut - $a) / 2);
            $frm = $evaluate($cut + ($b - $cut) / 2);
            $leftBudget = $tol * (($cut - $a) / ($b - $a));
            $stack = [
                [$cut, $b, $fc, $frm, $fb, $simpson($cut, $b, $fc, $frm, $fb), $tol - $leftBudget, 0],
                [$a, $cut, $fa, $flm, $fc, $simpson($a, $cut, $fa, $flm, $fc), $leftBudget, 0],
            ];
        } else {
            $stack = [[$a, $b, $fa, $fm, $fb, $simpson($a, $b, $fa, $fm, $fb), $tol, 0]];
        }
        $sum = 0.0;
        $error = 0.0;
        $converged = true;
        while ($stack !== []) {
            [$left, $right, $fl, $fc, $fr, $whole, $budget, $depth] = array_pop($stack);
            if ($evaluations + 2 > 8193) {
                $converged = false;
                break;
            }
            $centre = $left + ($right - $left) / 2;
            $lmid = $left + ($centre - $left) / 2;
            $rmid = $centre + ($right - $centre) / 2;
            $fml = $evaluate($lmid);
            $fmr = $evaluate($rmid);
            $sl = $simpson($left, $centre, $fl, $fml, $fc);
            $sr = $simpson($centre, $right, $fc, $fmr, $fr);
            $delta = $sl + $sr - $whole;
            if (! is_finite($delta) || ! is_finite($sum + $sl + $sr)) {
                throw ValidationException::withMessages(['integration' => 'Arithmetic overflow.']);
            }
            if (abs($delta) <= 15 * $budget) {
                $sum += $sl + $sr + $delta / 15;
                $error += abs($delta) / 15;
                if (! is_finite($sum) || ! is_finite($error)) {
                    throw ValidationException::withMessages(['integration' => 'Accumulation overflow.']);
                }
            } elseif ($depth >= 20 || $lmid === $left || $rmid === $right) {
                $converged = false;
                break;
            } else {
                $stack[] = [$centre, $right, $fc, $fmr, $fr, $sr, $budget / 2, $depth + 1];
                $stack[] = [$left, $centre, $fl, $fml, $fc, $sl, $budget / 2, $depth + 1];
            }
        }

        // Never publish an incomplete partial integral as a usable answer.
        return $this->result($converged ? $sign * $sum : null, $converged ? $error : null, $evaluations, $converged);
    }

    private function result(?float $value, ?float $error, int $evaluations, bool $converged): array
    {
        return ['status' => $converged ? 'computed' : 'not_converged', 'value' => $value,
            'evaluations' => $evaluations, 'verification' => ['status' => $converged ? 'estimated_error_only' : 'unverified',
                'estimated_absolute_error' => $error, 'limitations' => 'Adaptive sampling can miss narrow features or oscillations; this is not a certified error bound.']];
    }
}
