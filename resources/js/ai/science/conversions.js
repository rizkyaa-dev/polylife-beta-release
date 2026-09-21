import { finite, invalid, list, object } from './contract.js';

const ZERO = [0, 0, 0, 0, 0, 0, 0];
const UNITS = new Map([
    ['m', [1, 0, [0, 1, 0, 0, 0, 0, 0], 'm']], ['cm', [0.01, 0, [0, 1, 0, 0, 0, 0, 0], 'm']],
    ['mm', [0.001, 0, [0, 1, 0, 0, 0, 0, 0], 'm']], ['km', [1000, 0, [0, 1, 0, 0, 0, 0, 0], 'm']],
    ['s', [1, 0, [0, 0, 1, 0, 0, 0, 0], 's']], ['ms', [0.001, 0, [0, 0, 1, 0, 0, 0, 0], 's']],
    ['min', [60, 0, [0, 0, 1, 0, 0, 0, 0], 's']], ['h', [3600, 0, [0, 0, 1, 0, 0, 0, 0], 's']],
    ['kg', [1, 0, [1, 0, 0, 0, 0, 0, 0], 'kg']], ['g', [0.001, 0, [1, 0, 0, 0, 0, 0, 0], 'kg']],
    ['K', [1, 0, [0, 0, 0, 0, 1, 0, 0], 'K']], ['degC', [1, 273.15, [0, 0, 0, 0, 1, 0, 0], 'K']],
    ['A', [1, 0, [0, 0, 0, 1, 0, 0, 0], 'A']], ['mA', [0.001, 0, [0, 0, 0, 1, 0, 0, 0], 'A']],
    ['N', [1, 0, [1, 1, -2, 0, 0, 0, 0], 'N']], ['J', [1, 0, [1, 2, -2, 0, 0, 0, 0], 'J']],
    ['W', [1, 0, [1, 2, -3, 0, 0, 0, 0], 'W']], ['Pa', [1, 0, [1, -1, -2, 0, 0, 0, 0], 'Pa']],
    ['V', [1, 0, [1, 2, -3, -1, 0, 0, 0], 'V']],
    ['ohm', [1, 0, [1, 2, -3, -2, 0, 0, 0], 'ohm']], ['kohm', [1000, 0, [1, 2, -3, -2, 0, 0, 0], 'ohm']],
    ['F', [1, 0, [-1, -2, 4, 2, 0, 0, 0], 'F']], ['uF', [1e-6, 0, [-1, -2, 4, 2, 0, 0, 0], 'F']],
    ['rad/s', [1, 0, [0, 0, -1, 0, 0, 0, 0], 'rad/s']],
]);

const dimension = value => list(value, 7).map(finite);

function numberAt(inputs, path) {
    let value = inputs;
    for (const segment of path) {
        if ((typeof segment !== 'string' && !Number.isInteger(segment))
            || value === null || typeof value !== 'object' || !Object.hasOwn(value, segment)) {
            invalid('Conversion path does not resolve to a solver value.');
        }
        value = value[segment];
    }
    return finite(value);
}

function astTarget(node, path) {
    if (!node || typeof node !== 'object' || Array.isArray(node)) invalid('Conversion path does not resolve to an expression node.');
    if (path.length === 1 && path[0] === 'value' && node.op === 'const') {
        return [finite(node.value), node.dimension ?? ZERO];
    }
    if (path.length >= 2 && path[0] === 'args' && Number.isInteger(path[1])) {
        return astTarget(node.args?.[path[1]], path.slice(2));
    }
    invalid('Conversion path must end at a bounded constant value.');
}

function target(solver, inputs, path) {
    const dimensions = inputs.dimensions;
    if (!dimensions || typeof dimensions !== 'object' || Array.isArray(dimensions)) {
        invalid('Conversions require structured dimensions.');
    }
    if (solver === 'linear_system' && path.length === 3 && path[0] === 'matrix'
        && Number.isInteger(path[1]) && Number.isInteger(path[2])) {
        return [numberAt(inputs, path), dimensions.matrix?.[path[1]]?.[path[2]]];
    }
    if (solver === 'linear_system' && path.length === 2 && path[0] === 'rhs' && Number.isInteger(path[1])) {
        return [numberAt(inputs, path), dimensions.rhs?.[path[1]]];
    }
    if (['integrate', 'root_scalar'].includes(solver) && path.length === 1 && ['lower', 'upper'].includes(path[0])) {
        return [numberAt(inputs, path), dimensions.x];
    }
    if (solver === 'ode_ivp' && path.length === 1 && ['t_start', 't_end'].includes(path[0])) {
        return [numberAt(inputs, path), dimensions.t];
    }
    if (solver === 'ode_ivp' && path.length === 2 && path[0] === 'initial' && Number.isInteger(path[1])) {
        return [numberAt(inputs, path), dimensions.states?.[path[1]]];
    }
    if (['integrate', 'root_scalar'].includes(solver) && path[0] === 'expression') return astTarget(inputs.expression, path.slice(1));
    if (solver === 'ode_ivp' && path[0] === 'derivatives' && Number.isInteger(path[1])) {
        return astTarget(inputs.derivatives?.[path[1]], path.slice(2));
    }
    if (path[0] === 'outputs' && Number.isInteger(path[1]) && path[2] === 'expression') {
        return astTarget(inputs.outputs?.[path[1]]?.expression, path.slice(3));
    }
    invalid('Conversion path is not a supported solver numeric field.');
}

/** Verify source-unit provenance against the projected SI solver input. */
export function verifyConversions(solver, inputs) {
    if (!Object.hasOwn(inputs, 'conversions')) {
        return { status: 'unverified', reason: 'No structured source-unit conversions supplied.' };
    }
    const items = list(inputs.conversions, 0, 32);
    const verified = items.map(raw => {
        const item = object(raw);
        const path = list(item.path, 1, 32);
        if (typeof item.source_unit !== 'string') invalid('Malformed conversion provenance.');
        const source = finite(item.source_value);
        const definition = UNITS.get(item.source_unit);
        if (!definition) invalid('Source unit is unsupported.');
        const [targetValue, targetDimension] = target(solver, inputs, path);
        const expected = finite(source * definition[0] + definition[1]);
        if (source !== 0 && definition[1] === 0 && expected === 0) invalid('Source conversion underflows the supported numeric precision.');
        // Compare relative to the quantity, not to one unit of its SI dimension.
        if (targetValue !== expected && Math.abs(targetValue - expected) > 1e-12 * Math.max(Math.abs(targetValue), Math.abs(expected))) {
            invalid('Converted value does not match the solver input.');
        }
        const actualDimension = dimension(targetDimension);
        const expectedDimension = dimension(definition[2]);
        actualDimension.forEach((exponent, index) => {
            if (Math.abs(exponent - expectedDimension[index]) > 1e-9) invalid('Source unit does not match the solver input dimension.');
        });
        return { path, source_value: source, source_unit: item.source_unit, si_value: expected, si_unit: definition[3] };
    });
    return items.length === 0
        ? { status: 'none_declared', limitations: 'An empty list does not prove that the original problem used SI units.' }
        : { status: 'declared_conversions_checked', items: verified,
            limitations: 'Checks declared source values against bound SI inputs; it cannot prove omitted conversions or measurement validity.' };
}
