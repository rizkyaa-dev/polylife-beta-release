import { invalid } from './contract.js';
import { compileExpression } from './expression.js';
import { verifyOutputDimension } from './dimensions.js';

function requireSolverDependency(expression, slots, evaluate, bindings) {
    let referenced = false;
    const walk = node => {
        if (node.op === 'var' && slots.includes(node.name)) referenced = true;
        node.args?.forEach(walk);
    };
    walk(expression);
    if (!referenced) invalid('Every output must depend on a solver result slot.');
    if (Object.keys(bindings).length === 0) return;
    const baseline = evaluate(bindings);
    for (const [name, value] of Object.entries(bindings)) {
        const delta = Math.max(Math.abs(value) * 1e-6, 1e-9);
        for (const sign of [1, -1]) {
            const candidate = value + sign * delta;
            if (!Number.isFinite(candidate) || candidate === value) continue;
            try {
                if (evaluate({ ...bindings, [name]: candidate }) !== baseline) return;
            } catch { /* try another direction or result slot */ }
        }
    }
    invalid('Output is numerically independent of the solver result.');
}

/** Only original solver slots are bindings; output chains and source code are forbidden. */
export function projectScienceOutputs(solver, inputs, result) {
    if (inputs.outputs === undefined) return result;
    if (result.dimension_verification.status !== 'declared_dimensions_checked') invalid('Outputs require checked input dimensions.');
    const [values, dimensions] = solver === 'linear_system' ? [result.solution, inputs.dimensions.variables]
        : solver === 'integrate' ? [[result.value], [inputs.dimensions.output]]
            : solver === 'root_scalar' ? [[result.value], [inputs.dimensions.x]]
            : [result.final_state, inputs.dimensions.states];
    const bindings = Object.create(null);
    const units = Object.create(null);
    dimensions.forEach((dimension, i) => {
        units[`r${i}`] = dimension;
        if (result.status === 'computed') bindings[`r${i}`] = values[i];
    });
    const outputs = inputs.outputs.map(output => {
        const evaluate = compileExpression(output.expression, Object.keys(units));
        verifyOutputDimension(output.expression, units, output.dimension);
        requireSolverDependency(output.expression, Object.keys(units), evaluate, bindings);
        if (result.status !== 'computed') return null;
        return { name: output.name, value: evaluate(bindings), dimension: output.dimension,
            dimension_verification: 'declared_dimensions_checked' };
    });
    if (result.status !== 'computed') return result;
    result.outputs = outputs;
    result.output_verification = { status: 'bounded_arithmetic_and_dependency_checked',
        limitations: 'Observed numerical dependency rejects constant/cancelled outputs but cannot prove scientific relevance. Derived outputs do not improve solver accuracy or verify physical formulation.' };
    return result;
}
