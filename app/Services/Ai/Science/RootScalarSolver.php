<?php

namespace App\Services\Ai\Science;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Deterministic bracket-preserving scalar root solver. */
final class RootScalarSolver implements ScienceSolver
{
    private const MAX_ITERATIONS = 256;

    public function __construct(private readonly BoundedExpression $expressions) {}

    public function name(): string
    {
        return 'root_scalar';
    }

    public function specification(): array
    {
        return ['name' => $this->name(), 'method' => 'Bounded bisection on a sign-changing finite bracket with a residual-improvement safeguard',
            'inputs' => ['expression' => 'Arithmetic AST in variable x: const/var leaves; add,sub,mul,div,pow have exactly 2 args; neg,sin,cos,exp,log,sqrt have exactly 1 arg. Nest binary nodes for products of 3+ factors. Max 128 nodes and depth 12.', 'lower' => 'Finite lower bracket bound',
                'upper' => 'Finite upper bracket bound',
                'tolerance' => 'Optional absolute x-space target 1e-12..1e-2 (default 1e-8)'],
            'limits' => ['iterations' => self::MAX_ITERATIONS, 'evaluations' => self::MAX_ITERATIONS + 2],
            'limitations' => 'Requires a continuous real function and an explicit sign-changing bracket (or exact endpoint root). Continuity is not proven. Narrow intervals without improved residual are not published. Finds one bracketed root; an even-multiplicity root without a sign change is not detected.'];
    }

    public function solve(array $inputs): array
    {
        Validator::make($inputs, ['expression' => ['required', 'array'], 'lower' => ['required', 'numeric'],
            'upper' => ['required', 'numeric'], 'tolerance' => ['sometimes', 'numeric', 'between:0.000000000001,0.01']])->validate();
        $lower = ScienceNumbers::finite($inputs['lower']);
        $upper = ScienceNumbers::finite($inputs['upper']);
        $tolerance = ScienceNumbers::finite($inputs['tolerance'] ?? 1e-8);
        if ($lower >= $upper || ! is_finite($upper - $lower)) {
            throw ValidationException::withMessages(['bounds' => 'Root bracket must be finite, ordered and representable.']);
        }
        $function = $this->expressions->compile($inputs['expression']);
        $evaluations = 2;
        $fLower = $function(['x' => $lower]);
        $fUpper = $function(['x' => $upper]);
        $initialResidual = min(abs($fLower), abs($fUpper));
        if ($fLower === 0.0) {
            return $this->computed($lower, $fLower, 0.0, 0, $evaluations, $tolerance);
        }
        if ($fUpper === 0.0) {
            return $this->computed($upper, $fUpper, 0.0, 0, $evaluations, $tolerance);
        }
        if (($fLower < 0) === ($fUpper < 0)) {
            return ['status' => 'not_solved', 'reason' => 'root_not_bracketed', 'value' => null,
                'iterations' => 0, 'evaluations' => $evaluations, 'verification' => ['status' => 'unverified',
                    'limitations' => 'Equal endpoint signs do not exclude roots, but cannot certify a bracket for this method.']];
        }
        $iteration = 0;
        for ($iteration = 1; $iteration <= self::MAX_ITERATIONS; $iteration++) {
            $midpoint = $lower + ($upper - $lower) / 2;
            if ($midpoint === $lower || $midpoint === $upper) {
                break;
            }
            $fMidpoint = $function(['x' => $midpoint]);
            $evaluations++;
            $halfWidth = ($upper - $lower) / 2;
            // A shrinking sign-changing interval can converge to a pole. Require
            // residual improvement too; this is a safeguard, not a continuity proof.
            if ($fMidpoint === 0.0 || ($halfWidth <= $tolerance && abs($fMidpoint) < $initialResidual)) {
                return $this->computed($midpoint, $fMidpoint, 2 * $halfWidth, $iteration, $evaluations, $tolerance);
            }
            if ($halfWidth <= $tolerance) {
                return ['status' => 'not_converged', 'reason' => 'residual_not_reduced', 'value' => null,
                    'iterations' => $iteration, 'evaluations' => $evaluations,
                    'verification' => ['status' => 'unverified', 'limitations' => 'A small sign-changing interval without residual improvement may surround a discontinuity; no root is published.']];
            }
            if (($fLower < 0) === ($fMidpoint < 0)) {
                $lower = $midpoint;
                $fLower = $fMidpoint;
            } else {
                $upper = $midpoint;
                $fUpper = $fMidpoint;
            }
        }

        return ['status' => 'not_converged', 'reason' => 'iteration_or_precision_limit', 'value' => null,
            'iterations' => min($iteration, self::MAX_ITERATIONS), 'evaluations' => $evaluations,
            'verification' => ['status' => 'unverified', 'limitations' => 'Iteration, floating-point resolution, or residual-improvement requirement was not met. Continuity is not proven.']];
    }

    private function computed(float $value, float $residual, float $width, int $iterations, int $evaluations, float $tolerance): array
    {
        return ['status' => 'computed', 'value' => $value, 'iterations' => $iterations, 'evaluations' => $evaluations,
            'verification' => ['status' => 'bracketed_interval_checked', 'absolute_bracket_width' => $width,
                'absolute_residual' => abs($residual), 'x_tolerance' => $tolerance,
                'limitations' => 'Bracket width bounds x error only under continuity; residual size is scale-dependent and does not validate the physical model or root uniqueness.']];
    }
}
