import { finite, invalid, list, object, SCIENCE_KERNEL_VERSION, tolerance } from './contract.js';
import { compileExpression } from './expression.js';
import { verifyDimensions } from './dimensions.js';
import { prepareScienceInput } from './input.js';
import { projectScienceOutputs } from './outputs.js';
import { verifyConversions } from './conversions.js';

const maxAbs = values => Math.max(...values.map(Math.abs));

function linearSystem(inputs, checkpoint) {
    const n = list(inputs.matrix, 1, 32).length;
    const original = inputs.matrix.map(row => list(row, n).map(finite));
    const rhs = list(inputs.rhs, n).map(finite);
    const a = original.map(row => [...row]);
    const b = [...rhs];
    const scales = a.map(maxAbs);
    for (let k = 0; k < n; k++) {
        checkpoint();
        let pivot = k;
        let ratio = -1;
        for (let i = k; i < n; i++) {
            const candidate = scales[i] === 0 ? 0 : Math.abs(a[i][k]) / scales[i];
            if (candidate > ratio) { ratio = candidate; pivot = i; }
        }
        if (ratio <= 1e-14) return { status: 'not_solved', reason: 'singular_or_numerically_ill_conditioned', verification: { status: 'unverified' } };
        [a[k], a[pivot]] = [a[pivot], a[k]];
        [b[k], b[pivot]] = [b[pivot], b[k]];
        [scales[k], scales[pivot]] = [scales[pivot], scales[k]];
        for (let i = k + 1; i < n; i++) {
            const factor = finite(a[i][k] / a[k][k]);
            for (let j = k; j < n; j++) a[i][j] = finite(a[i][j] - factor * a[k][j]);
            b[i] = finite(b[i] - factor * b[k]);
        }
    }
    const x = Array(n).fill(0);
    for (let i = n - 1; i >= 0; i--) {
        let sum = b[i];
        for (let j = i + 1; j < n; j++) sum -= a[i][j] * x[j];
        x[i] = finite(sum / a[i][i]);
    }
    let residual = 0;
    let normA = 0;
    original.forEach((row, i) => {
        const sum = row.reduce((total, value, j) => total + value * x[j], 0);
        residual = Math.max(residual, Math.abs(finite(sum - rhs[i])));
        normA = Math.max(normA, finite(row.reduce((total, value) => total + Math.abs(value), 0)));
    });
    const denominator = finite(normA * maxAbs(x) + maxAbs(rhs));
    const error = denominator === 0 ? 0 : finite(residual / denominator);
    return { status: 'computed', solution: x, verification: {
        status: error <= 1e-10 ? 'numerically_checked' : 'failed', relative_backward_error: error, tolerance: 1e-10,
        limitations: 'Small residual does not prove model correctness or forward accuracy for ill-conditioned systems.',
    } };
}

function integration(inputs, checkpoint) {
    let a = finite(inputs.lower);
    let b = finite(inputs.upper);
    finite(b - a);
    const tol = tolerance(inputs.tolerance, 1e-7, 1e-10);
    const f = compileExpression(inputs.expression);
    let evaluations = 0;
    const result = (value, error, converged) => ({
        status: converged ? 'computed' : 'not_converged', value, evaluations,
        verification: { status: converged ? 'estimated_error_only' : 'unverified', estimated_absolute_error: error,
            limitations: 'Adaptive sampling can miss narrow features or oscillations; this is not a certified error bound.' },
    });
    if (a === b) return result(0, 0, true);
    const sign = a < b ? 1 : -1;
    if (sign < 0) [a, b] = [b, a];
    const evaluate = x => { evaluations++; return f({ x }); };
    const simpson = (left, right, fl, fc, fr) => {
        const scale = Math.max(Math.abs(fl), Math.abs(fc), Math.abs(fr));
        if (scale === 0) return 0;
        const average = (fl / scale + 4 * (fc / scale) + fr / scale) / 6;
        const product = (right - left) * scale;
        return Number.isFinite(product) ? product * average : (right - left) * (scale * average);
    };
    const fa = evaluate(a);
    // Preserve the midpoint domain check; unequal seeds are additional sampling.
    const fm = evaluate(a + (b - a) / 2);
    const fb = evaluate(b);
    // Unequal seeds mitigate dyadic aliases, not arbitrary sampling blind spots.
    const cut = a + (b - a) * 0.3819660112501051;
    let stack;
    if (cut > a && cut < b) {
        const fc = evaluate(cut);
        const flm = evaluate(a + (cut - a) / 2);
        const frm = evaluate(cut + (b - cut) / 2);
        const leftBudget = tol * ((cut - a) / (b - a));
        stack = [
            [cut, b, fc, frm, fb, simpson(cut, b, fc, frm, fb), tol - leftBudget, 0],
            [a, cut, fa, flm, fc, simpson(a, cut, fa, flm, fc), leftBudget, 0],
        ];
    } else {
        stack = [[a, b, fa, fm, fb, simpson(a, b, fa, fm, fb), tol, 0]];
    }
    let sum = 0;
    let error = 0;
    while (stack.length) {
        checkpoint();
        const [left, right, fl, fc, fr, whole, budget, depth] = stack.pop();
        if (evaluations + 2 > 8193) return result(null, null, false);
        const centre = left + (right - left) / 2;
        const lmid = left + (centre - left) / 2;
        const rmid = centre + (right - centre) / 2;
        const fml = evaluate(lmid);
        const fmr = evaluate(rmid);
        const sl = simpson(left, centre, fl, fml, fc);
        const sr = simpson(centre, right, fc, fmr, fr);
        const delta = finite(sl + sr - whole);
        finite(sum + sl + sr);
        if (Math.abs(delta) <= 15 * budget) {
            sum = finite(sum + sl + sr + delta / 15);
            error = finite(error + Math.abs(delta) / 15);
        } else if (depth >= 20 || lmid === left || rmid === right) {
            return result(null, null, false);
        } else {
            stack.push([centre, right, fc, fmr, fr, sr, budget / 2, depth + 1]);
            stack.push([left, centre, fl, fml, fc, sl, budget / 2, depth + 1]);
        }
    }
    return result(sign * sum, error, true);
}

function rootScalar(inputs, checkpoint) {
    let lower = finite(inputs.lower);
    let upper = finite(inputs.upper);
    finite(upper - lower);
    if (lower >= upper) invalid('Root bracket must be ordered.');
    const tol = tolerance(inputs.tolerance, 1e-8, 1e-12);
    const f = compileExpression(inputs.expression);
    let evaluations = 2;
    let fLower = f({ x: lower });
    const fUpper = f({ x: upper });
    const initialResidual = Math.min(Math.abs(fLower), Math.abs(fUpper));
    const computed = (value, residual, width, iterations) => ({ status: 'computed', value, iterations, evaluations,
        verification: { status: 'bracketed_interval_checked', absolute_bracket_width: width,
            absolute_residual: Math.abs(residual), x_tolerance: tol,
            limitations: 'Bracket width bounds x error only under continuity; residual size is scale-dependent and does not validate the physical model or root uniqueness.' } });
    if (fLower === 0) return computed(lower, fLower, 0, 0);
    if (fUpper === 0) return computed(upper, fUpper, 0, 0);
    if ((fLower < 0) === (fUpper < 0)) return { status: 'not_solved', reason: 'root_not_bracketed', value: null,
        iterations: 0, evaluations, verification: { status: 'unverified',
            limitations: 'Equal endpoint signs do not exclude roots, but cannot certify a bracket for this method.' } };
    let iterations = 0;
    for (let iteration = 1; iteration <= 256; iteration++) {
        iterations = iteration;
        checkpoint();
        const midpoint = lower + (upper - lower) / 2;
        if (midpoint === lower || midpoint === upper) break;
        const fMidpoint = f({ x: midpoint });
        evaluations++;
        const halfWidth = (upper - lower) / 2;
        // Reject shrinking brackets without residual improvement (e.g. an off-grid pole).
        if (fMidpoint === 0 || (halfWidth <= tol && Math.abs(fMidpoint) < initialResidual)) return computed(midpoint, fMidpoint, 2 * halfWidth, iteration);
        if (halfWidth <= tol) return { status: 'not_converged', reason: 'residual_not_reduced', value: null,
            iterations: iteration, evaluations, verification: { status: 'unverified',
                limitations: 'A small sign-changing interval without residual improvement may surround a discontinuity; no root is published.' } };
        if ((fLower < 0) === (fMidpoint < 0)) { lower = midpoint; fLower = fMidpoint; } else upper = midpoint;
    }
    return { status: 'not_converged', reason: 'iteration_or_precision_limit', value: null,
        iterations, evaluations, verification: { status: 'unverified',
            limitations: 'Iteration, floating-point resolution, or residual-improvement requirement was not met. Continuity is not proven.' } };
}

function rk4(derivatives, t, y, h) {
    const evaluate = (time, state) => {
        const values = { t: time };
        state.forEach((value, i) => { values[`y${i}`] = value; });
        return derivatives.map(f => f(values));
    };
    const advance = (slope, scale) => y.map((value, i) => finite(value + scale * h * slope[i]));
    const k1 = evaluate(t, y);
    const k2 = evaluate(t + h / 2, advance(k1, 0.5));
    const k3 = evaluate(t + h / 2, advance(k2, 0.5));
    const k4 = evaluate(t + h, advance(k3, 1));
    return y.map((value, i) => finite(value + h / 6 * (k1[i] + 2 * k2[i] + 2 * k3[i] + k4[i])));
}

function ode(inputs, checkpoint) {
    let y = list(inputs.initial, 1, 4).map(finite);
    const variables = ['t', ...y.map((_, i) => `y${i}`)];
    const derivatives = list(inputs.derivatives, y.length).map(expression => compileExpression(expression, variables));
    const start = finite(inputs.t_start);
    const end = finite(inputs.t_end);
    const span = finite(end - start);
    if (span <= 0) invalid('Time interval must be positive.');
    const tol = tolerance(inputs.tolerance, 1e-6, 1e-8);
    let t = start;
    let step = span / 32;
    let sampleIndex = 1;
    let nextSample = Math.min(end, start + span * sampleIndex / 64);
    let attempts = 0;
    let accepted = 0;
    let maxError = 0;
    const samples = [{ t, state: y }];
    const failure = reason => ({ status: 'not_converged', reason, attempts, final_state: null, samples: [], verification: { status: 'unverified' } });
    while (t < end && attempts < 2048) {
        checkpoint();
        attempts++;
        const h = Math.min(step, end - t, nextSample - t);
        if (h <= 0 || t + h === t) return failure('time_step_underflow');
        const whole = rk4(derivatives, t, y, h);
        const half = rk4(derivatives, t, y, h / 2);
        const fine = rk4(derivatives, t + h / 2, half, h / 2);
        const error = finite(Math.max(...fine.map((value, i) => Math.abs(value - whole[i]) / 15)));
        if (error <= tol) {
            y = fine.map((value, i) => finite(value + (value - whole[i]) / 15));
            t = h === nextSample - t ? nextSample : t + h;
            accepted++;
            maxError = Math.max(maxError, error);
            if (t >= nextSample) {
                samples.push({ t, state: y });
                sampleIndex++;
                nextSample = sampleIndex >= 64 ? end : Math.min(end, start + span * sampleIndex / 64);
            }
        }
        const factor = error === 0 ? 2 : Math.min(2, Math.max(0.2, 0.9 * (tol / error) ** 0.2));
        step = finite(h * factor);
    }
    if (t < end) return failure('iteration_budget_exceeded');
    return { status: 'computed', final_state: y, samples: samples.slice(0, 65), attempts, accepted_steps: accepted,
        verification: { status: 'estimated_local_error_only', max_estimated_local_error: maxError, tolerance: tol,
            tolerance_interpretation: 'Absolute componentwise target in each state SI unit; not dimensionless or a global bound.',
            limitations: 'Local estimates do not certify global accuracy, stability or physical correctness.' } };
}

const solvers = new Map([['linear_system', linearSystem], ['integrate', integration], ['ode_ivp', ode], ['root_scalar', rootScalar]]);

export function solveScience(name, inputs, checkpoint = () => {}) {
    const solver = solvers.get(name);
    if (!solver) invalid('Solver is unavailable.');
    object(inputs);
    inputs = prepareScienceInput(name, inputs);
    checkpoint();
    const conversionVerification = verifyConversions(name, inputs);
    const result = solver(inputs, checkpoint);
    result.dimension_verification = verifyDimensions(name, inputs);
    result.conversion_verification = conversionVerification;
    result.kernel_version = SCIENCE_KERNEL_VERSION;
    return projectScienceOutputs(name, inputs, result);
}
