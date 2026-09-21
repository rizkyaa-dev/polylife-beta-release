import { finite, invalid, list, object } from './contract.js';
import { compileExpression } from './expression.js';

export const DIMENSION_BASIS = ['mass', 'length', 'time', 'current', 'temperature', 'amount', 'luminous_intensity'];
const zero = () => Array(7).fill(0);
const vector = value => list(value, 7).map(exponent => {
    finite(exponent);
    if (Math.abs(exponent) > 32) invalid('Dimension exponent limit exceeded.');
    return exponent;
});
const combine = (a, b, sign) => vector(a.map((value, i) => value + sign * b[i]));
const equal = (a, b) => {
    if (a.some((value, i) => Math.abs(value - b[i]) > 1e-9)) invalid('Inconsistent dimensions.');
};

function expressionDimension(node, variables) {
    if (node.op === 'const') return node.dimension === undefined ? zero() : vector(node.dimension);
    if (node.op === 'var') return variables[node.name] ?? invalid('Missing variable dimension.');
    const a = expressionDimension(node.args[0], variables);
    const b = node.args[1] && expressionDimension(node.args[1], variables);
    switch (node.op) {
        case 'add': case 'sub': equal(a, b); return a;
        case 'mul': return combine(a, b, 1);
        case 'div': return combine(a, b, -1);
        case 'neg': return a;
        case 'sqrt': return vector(a.map(value => value / 2));
        case 'pow': {
            equal(b, zero());
            if (a.every(value => value === 0)) return zero();
            const exponent = compileExpression(node.args[1], [])({});
            return vector(a.map(value => value * exponent));
        }
        default: equal(a, zero()); return zero();
    }
}

export function verifyOutputDimension(expression, variables, expected) {
    compileExpression(expression, Object.keys(variables));
    equal(expressionDimension(expression, variables), vector(expected));
}

/** Numerical solvers validate AST shape before this dimensional traversal. */
export function verifyDimensions(solver, inputs) {
    if (inputs.dimensions === undefined) return { status: 'unverified', reason: 'No structured dimensional declarations supplied.' };
    const d = object(inputs.dimensions);
    if (solver === 'integrate') {
        const x = vector(d.x);
        equal(combine(expressionDimension(inputs.expression, { x }), x, 1), vector(d.output));
    } else if (solver === 'root_scalar') {
        const x = vector(d.x);
        equal(expressionDimension(inputs.expression, { x }), vector(d.output));
    } else if (solver === 'ode_ivp') {
        const states = list(d.states, inputs.initial.length).map(vector);
        const variables = { t: vector(d.t) };
        states.forEach((state, i) => { variables[`y${i}`] = state; });
        inputs.derivatives.forEach((expression, i) => equal(expressionDimension(expression, variables), combine(states[i], variables.t, -1)));
    } else if (solver === 'linear_system') {
        const n = inputs.matrix.length;
        const variables = list(d.variables, n).map(vector);
        const rhs = list(d.rhs, n).map(vector);
        list(d.matrix, n).forEach((row, i) => list(row, n).forEach((coefficient, j) => equal(combine(vector(coefficient), variables[j], 1), rhs[i])));
    } else {
        return { status: 'unverified', reason: 'No dimensional verifier for this solver.' };
    }
    return {
        status: 'declared_dimensions_checked', basis: DIMENSION_BASIS,
        limitations: 'Checks consistency of declared dimensions, not measured units, scale conversions or physical truth.',
    };
}
