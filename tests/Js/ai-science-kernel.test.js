import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { solveScience } from '../../resources/js/ai/science/solvers.js';
import { compileExpression } from '../../resources/js/ai/science/expression.js';
import { prepareScienceInput } from '../../resources/js/ai/science/input.js';
import { verifyConversions } from '../../resources/js/ai/science/conversions.js';

test('tiny conversion rejects zero and wrong scale instead of applying a unit-sized absolute floor', () => {
    for (const [value, source] of [[0, 1e-12], [1e-15, 1e-12], [0, 1e-320]]) {
        assert.throws(() => verifyConversions('integrate', {
            expression: { op: 'const', value, dimension: [-1, -2, 4, 2, 0, 0, 0] },
            dimensions: { x: [0, 0, 0, 0, 0, 0, 0] },
            conversions: [{ path: ['expression', 'value'], source_value: source, source_unit: 'uF' }],
        }), error => error.code === 'invalid_input');
    }
});

test('off-grid pole is not published as a root', () => {
    const result = solveScience('root_scalar', { lower: 0, upper: 1, tolerance: 1e-8,
        expression: { op: 'div', args: [{ op: 'const', value: 1 },
            { op: 'sub', args: [{ op: 'var', name: 'x' }, { op: 'const', value: 0.123456789 }] }] },
    });
    assert.equal(result.status, 'not_converged');
    assert.equal(result.value, null);
});

const fixtures = JSON.parse(readFileSync(new URL('../Fixtures/science-kernel.json', import.meta.url)));
const close = (actual, expected, tolerance = 1e-7) => assert.ok(Math.abs(actual - expected) <= tolerance, `${actual} != ${expected}`);

test('server-built RC domain inputs execute in the browser kernel without source text or physical trust claims', () => {
    const process = spawnSync('php', ['tests/Support/science-domain-reference.php'], {
        encoding: 'utf8', timeout: 15000,
    });
    assert.equal(process.status, 0, process.stderr || process.stdout);
    const compiled = JSON.parse(process.stdout);
    const prepared = prepareScienceInput(compiled.solver, compiled.inputs);
    assert.ok(!JSON.stringify(prepared).includes('R1=1 kohm'));
    const result = solveScience(compiled.solver, compiled.inputs);
    close(result.final_state[0], 8.324986336363539, 1e-7);
    close(result.final_state[1], 4.979720827625142, 1e-7);
    result.final_state.forEach((value, i) => close(value, compiled.reference.final_state[i], 1e-11));
    assert.deepEqual(result.conversion_verification, compiled.reference.conversion_verification);
    assert.deepEqual(result.dimension_verification, compiled.reference.dimension_verification);
    assert.equal(result.model_evidence, undefined);
    assert.equal(result.model_verification, undefined);
});

test('non-dyadic quadrature seeds resolve reproduced harmonic aliases with PHP parity', () => {
    const cases = [8, 12, 24, 64].map(frequency => ({ solver: 'integrate', inputs: {
        lower: 0, upper: 1, tolerance: 1e-8, expression: { op: 'cos', args: [
            { op: 'mul', args: [{ op: 'const', value: frequency * Math.PI }, { op: 'var', name: 'x' }] },
        ] },
    } }));
    const process = spawnSync('php', ['tests/Support/science-kernel-reference.php'], {
        input: JSON.stringify(cases), encoding: 'utf8', timeout: 15000,
    });
    assert.equal(process.status, 0, process.stderr || process.stdout);
    JSON.parse(process.stdout).forEach((php, i) => {
        const result = solveScience(cases[i].solver, cases[i].inputs);
        assert.equal(result.status, 'computed');
        assert.equal(php.status, 'computed');
        close(result.value, 0, 1e-8);
        close(php.value, 0, 1e-8);
        close(result.value, php.value, 1e-11);
    });
});

for (const fixture of fixtures) {
    test(`independent reference: ${fixture.name}`, () => {
        const result = solveScience(fixture.solver, fixture.inputs);
        assert.equal(result.status, fixture.expected.status);
        if (fixture.expected.solution) result.solution.forEach((value, i) => close(value, fixture.expected.solution[i], 1e-12));
        if (fixture.expected.value !== undefined) close(result.value, fixture.expected.value, fixture.expected.tolerance ?? 1e-7);
        fixture.expected.outputs?.forEach((value, i) => close(result.outputs[i].value, value, fixture.expected.tolerance ?? 1e-7));
        if (fixture.expected.final_state) result.final_state.forEach((value, i) => close(value, fixture.expected.final_state[i], fixture.expected.tolerance ?? 1e-7));
        if (result.samples?.length) {
            assert.equal(result.samples.length, 65);
            assert.equal(result.samples.at(-1).t, fixture.inputs.t_end);
            assert.deepEqual(result.samples.at(-1).state, result.final_state);
            result.samples.slice(1).forEach((sample, i) => assert.ok(sample.t > result.samples[i].t));
        }
        if (result.status === 'not_converged') { assert.equal(result.final_state, null); assert.deepEqual(result.samples, []); }
    });
}

test('PHP/browser kernel parity across scientific and resource-limited cases', () => {
    const process = spawnSync('php', ['tests/Support/science-kernel-reference.php'], {
        cwd: fileURLToPath(new URL('../../', import.meta.url)), input: JSON.stringify(fixtures), encoding: 'utf8', timeout: 15000,
    });
    assert.equal(process.status, 0, process.stderr || process.stdout);
    const reference = JSON.parse(process.stdout);
    fixtures.forEach((fixture, i) => {
        const result = solveScience(fixture.solver, fixture.inputs);
        const php = reference[i];
        assert.equal(result.status, php.status, fixture.name);
        assert.equal(result.kernel_version, php.kernel_version);
        assert.deepEqual(result.dimension_verification, php.dimension_verification);
        assert.deepEqual(result.conversion_verification, php.conversion_verification);
        assert.deepEqual(result.output_verification, php.output_verification);
        if (result.outputs) result.outputs.forEach((output, j) => {
            close(output.value, php.outputs[j].value, 1e-9);
            assert.equal(output.name, php.outputs[j].name);
            assert.deepEqual(output.dimension, php.outputs[j].dimension);
            assert.equal(output.dimension_verification, php.outputs[j].dimension_verification);
        });
        assert.equal(result.verification.status, php.verification.status);
        for (const field of ['solution', 'final_state']) {
            if (result[field]) result[field].forEach((value, j) => close(value, php[field][j], 1e-11));
        }
        if (result.value !== undefined && result.value !== null) close(result.value, php.value, fixture.expected.tolerance ?? 1e-11);
    });
});

test('PHP/browser reject invalid conversion provenance identically', () => {
    const fixture = fixtures.find(item => item.name === 'unit-converted-linear-current');
    const cases = [];
    for (const mutate of [
        input => { input.matrix[0][0] = 2; },
        input => { input.conversions[0].source_unit = 'cm'; },
        input => { input.conversions[0].source_unit = 'bitcoin'; },
        input => { input.conversions[0].path = ['outputs', 0, 'name']; },
        input => { input.conversions[0].path = ['matrix', -1, 0]; },
        input => { input.conversions[0].source_value = '2'; },
        input => { delete input.dimensions; },
        input => { input.conversions = Array.from({ length: 33 }, () => structuredClone(input.conversions[0])); },
    ]) {
        const item = structuredClone(fixture);
        mutate(item.inputs);
        cases.push(item);
    }
    const process = spawnSync('php', ['tests/Support/science-kernel-reference.php'], {
        cwd: fileURLToPath(new URL('../../', import.meta.url)), input: JSON.stringify(cases), encoding: 'utf8', timeout: 15000,
    });
    assert.equal(process.status, 0, process.stderr || process.stdout);
    JSON.parse(process.stdout).forEach(result => assert.equal(result.error, 'invalid_input'));
    cases.forEach(({ solver, inputs }) => assert.throws(() => solveScience(solver, inputs), error => error.code === 'invalid_input'));
});

test('input projection drops unbounded metadata before transfer', () => {
    const input = { matrix: [[2]], rhs: [4], metadata: { arbitrary: Array(100000).fill('large') } };
    assert.deepEqual(prepareScienceInput('linear_system', input), { matrix: [[2]], rhs: [4] });
    assert.throws(() => prepareScienceInput('linear_system', { matrix: Array(33).fill([1]), rhs: [1] }), /dimension/);
    assert.throws(() => prepareScienceInput('linear_system', { matrix: Array(1), rhs: [1] }), /Sparse/);
});

test('PHP/browser reject invalid scientific output contracts', () => {
    const fixture = fixtures.find(item => item.name === 'radiative-temperature-output');
    const cases = [];
    for (const mutate of [
        input => { input.outputs[0].dimension = [0, 1, 0, 0, 0, 0, 0]; },
        input => { delete input.dimensions; },
        input => { input.outputs[0].expression = { op: 'var', name: 'r1' }; },
        input => { input.outputs[0].expression = { op: 'var', name: '__proto__' }; },
        input => { input.outputs.push(structuredClone(input.outputs[0])); },
        input => { input.outputs = Array.from({ length: 9 }, (_, i) => ({ ...input.outputs[0], name: `result${i}` })); },
        input => { input.outputs[0].name = '<script>'; },
        input => { input.outputs[0].expression = { op: 'div', args: [{ op: 'var', name: 'r0' }, { op: 'const', value: 0 }] }; },
        input => { input.outputs[0].expression = { op: 'eval', args: ['fetch("/logout")'] }; },
        input => { input.outputs[0].expression = { op: 'const', value: 557.0334974621942, dimension: [0, 0, 0, 0, 1, 0, 0] }; },
        input => { input.outputs[0].expression = { op: 'add', args: [
            { op: 'sub', args: [
                { op: 'pow', args: [{ op: 'var', name: 'r0' }, { op: 'const', value: 0.25 }] },
                { op: 'pow', args: [{ op: 'var', name: 'r0' }, { op: 'const', value: 0.25 }] },
            ] },
            { op: 'const', value: 557.0334974621942, dimension: [0, 0, 0, 0, 1, 0, 0] },
        ] }; },
        input => { input.matrix = [[0]]; input.outputs = []; },
        input => { input.matrix = [[0]]; delete input.dimensions; },
    ]) {
        const item = structuredClone(fixture);
        mutate(item.inputs);
        cases.push(item);
    }
    const process = spawnSync('php', ['tests/Support/science-kernel-reference.php'], {
        cwd: fileURLToPath(new URL('../../', import.meta.url)), input: JSON.stringify(cases), encoding: 'utf8', timeout: 15000,
    });
    assert.equal(process.status, 0, process.stderr || process.stdout);
    JSON.parse(process.stdout).forEach(result => assert.equal(result.error, 'invalid_input'));
    cases.forEach(({ solver, inputs }) => assert.throws(() => solveScience(solver, inputs), error => error.code === 'invalid_input'));
});

test('PHP/browser kernels both reject malformed numbers, dimensions and expression plans', () => {
    const cases = [
        { solver: 'linear_system', inputs: { matrix: [[1]], rhs: ['2'] } },
        { solver: 'linear_system', inputs: { matrix: { first: [1] }, rhs: [2] } },
        { solver: 'integrate', inputs: { expression: { op: 'const', value: 1 }, lower: '0', upper: 1 } },
        { solver: 'integrate', inputs: { expression: { op: 'eval', args: ['1+1'] }, lower: 0, upper: 1 } },
        { solver: 'ode_ivp', inputs: { initial: [1], derivatives: [{ op: 'var', name: 'y3' }], t_start: 0, t_end: 1 } },
        { solver: 'ode_ivp', inputs: { initial: [1], derivatives: [{ op: 'const', value: 1 }], t_start: 0, t_end: 1, tolerance: '0.001' } },
        { solver: 'linear_system', inputs: { matrix: [[1]], rhs: [2], dimensions: { variables: 'metres', rhs: [], matrix: [] } } },
        { solver: 'linear_system', inputs: { matrix: [[1]], rhs: [2], dimensions: null } },
        { solver: 'integrate', inputs: { expression: { op: 'const', value: 1, dimension: null }, lower: 0, upper: 1 } },
        { solver: 'integrate', inputs: { expression: { op: 'pow', args: [
            { op: 'const', value: 16, dimension: [0, 0, 0, 0, 4, 0, 0] }, { op: 'var', name: 'x' },
        ] }, lower: 0, upper: 1, dimensions: { x: [0, 0, 0, 0, 0, 0, 0], output: [0, 0, 0, 0, 1, 0, 0] } } },
        { solver: 'integrate', inputs: { expression: { op: 'pow', args: [
            { op: 'const', value: 16, dimension: [0, 0, 0, 0, 4, 0, 0] },
            { op: 'div', args: [{ op: 'const', value: 1, dimension: [0, 1, 0, 0, 0, 0, 0] }, { op: 'const', value: 4 }] },
        ] }, lower: 0, upper: 1, dimensions: { x: [0, 0, 0, 0, 0, 0, 0], output: [0, 0, 0, 0, 1, 0, 0] } } },
    ];
    const process = spawnSync('php', ['tests/Support/science-kernel-reference.php'], {
        cwd: fileURLToPath(new URL('../../', import.meta.url)), input: JSON.stringify(cases), encoding: 'utf8', timeout: 15000,
    });
    assert.equal(process.status, 0, process.stderr || process.stdout);
    JSON.parse(process.stdout).forEach(result => assert.equal(result.error, 'invalid_input'));
    cases.forEach(({ solver, inputs }) => assert.throws(() => solveScience(solver, inputs), error => error.code === 'invalid_input'));
});

test('source code, coercion, expression domain errors and malformed ASTs are rejected', () => {
    assert.throws(() => compileExpression({ op: 'eval', args: ['fetch("/logout")'] }), /operator/);
    assert.throws(() => compileExpression({ op: 'const', value: '1' }), /finite/);
    assert.throws(() => compileExpression({ op: 'var', name: '__proto__' }), /variable/);
    assert.throws(() => compileExpression({ op: 'var', name: 'x' })({ x: '2' }), /finite/);
    for (const [op, value] of [['sqrt', -1], ['log', 0], ['exp', 1000]]) {
        assert.throws(() => compileExpression({ op, args: [{ op: 'const', value }] })({}), /error|finite/);
    }
    let node = { op: 'const', value: 1 };
    for (let i = 0; i < 14; i++) node = { op: 'neg', args: [node] };
    assert.throws(() => compileExpression(node), /resource/);
});

test('dimension violations, overflow and singular integrands never produce results', () => {
    const work = structuredClone(fixtures.find(fixture => fixture.name === 'work-integral'));
    work.inputs.expression.args[0].args[0].dimension[1] = -1;
    assert.throws(() => solveScience(work.solver, work.inputs), /dimensions/);
    assert.throws(() => solveScience('linear_system', { matrix: [[1e308, 1e308], [1e308, -1e308]], rhs: [1, 1] }), /finite/);
    assert.throws(() => solveScience('integrate', { lower: -1, upper: 1, expression: { op: 'div', args: [{ op: 'const', value: 1 }, { op: 'var', name: 'x' }] } }), /zero/);
    assert.throws(() => solveScience('root_scalar', { lower: -1, upper: 1,
        expression: { op: 'div', args: [{ op: 'const', value: 1 }, { op: 'var', name: 'x' }] } }), /zero/);
});

test('reverse integral, empty interval and fractional sampling endpoints', () => {
    const expression = { op: 'pow', args: [{ op: 'var', name: 'x' }, { op: 'const', value: 2 }] };
    close(solveScience('integrate', { expression, lower: 3, upper: 0 }).value, -9, 1e-12);
    assert.equal(solveScience('integrate', { expression, lower: 1, upper: 1 }).value, 0);
    const result = solveScience('ode_ivp', { initial: [0], derivatives: [{ op: 'const', value: 1 }], t_start: 0.1, t_end: 0.3 });
    assert.equal(result.samples.length, 65);
    assert.equal(result.samples.at(-1).t, 0.3);
});

test('checkpoint interrupts computation without publishing a partial result', () => {
    let checks = 0;
    const fixture = fixtures.find(item => item.name === 'damped-oscillator');
    assert.throws(() => solveScience(fixture.solver, fixture.inputs, () => { if (++checks === 4) throw new Error('cancelled'); }), /cancelled/);
});

test('deterministic generated models agree with analytic oracles and the PHP kernel', () => {
    let state = 0x5c1e7ce;
    const random = () => ((state = (1664525 * state + 1013904223) >>> 0) / 0x100000000);
    const generated = [];
    for (let sample = 0; sample < 25; sample++) {
        const n = 1 + sample % 5;
        const expected = Array.from({ length: n }, () => random() * 4 - 2);
        const matrix = Array.from({ length: n }, (_, i) => {
            const row = Array.from({ length: n }, (_, j) => i === j ? 0 : random() - 0.5);
            row[i] = row.reduce((sum, value, j) => j === i ? sum : sum + Math.abs(value), 0) + 1 + random();
            return row;
        });
        const rhs = matrix.map(row => row.reduce((sum, value, i) => sum + value * expected[i], 0));
        generated.push({ name: `generated-linear-${sample}`, solver: 'linear_system', inputs: { matrix, rhs }, expected: { status: 'computed', solution: expected } });

        const coefficients = [random() * 4 - 2, random() * 4 - 2, random() * 4 - 2];
        const upper = 0.25 + random() * 3;
        const expression = { op: 'add', args: [{ op: 'add', args: [
            { op: 'const', value: coefficients[0] },
            { op: 'mul', args: [{ op: 'const', value: coefficients[1] }, { op: 'var', name: 'x' }] },
        ] }, { op: 'mul', args: [{ op: 'const', value: coefficients[2] },
            { op: 'pow', args: [{ op: 'var', name: 'x' }, { op: 'const', value: 2 }] }] }] };
        const integral = coefficients[0] * upper + coefficients[1] * upper ** 2 / 2 + coefficients[2] * upper ** 3 / 3;
        generated.push({ name: `generated-integral-${sample}`, solver: 'integrate',
            inputs: { expression, lower: 0, upper, tolerance: 1e-9 }, expected: { status: 'computed', value: integral } });

        const root = random() * 20 - 10;
        const slope = 0.1 + random() * 5;
        const radius = 0.5 + random() * 5;
        generated.push({ name: `generated-root-${sample}`, solver: 'root_scalar', inputs: {
            expression: { op: 'mul', args: [{ op: 'const', value: slope },
                { op: 'sub', args: [{ op: 'var', name: 'x' }, { op: 'const', value: root }] }] },
            lower: root - radius, upper: root + radius, tolerance: 1e-10,
        }, expected: { status: 'computed', value: root } });

        const initial = 0.2 + random() * 3;
        const rate = -(0.1 + random() * 1.9);
        const duration = 0.2 + random() * 3;
        generated.push({ name: `generated-ode-${sample}`, solver: 'ode_ivp', inputs: {
            initial: [initial], derivatives: [{ op: 'mul', args: [{ op: 'const', value: rate }, { op: 'var', name: 'y0' }] }],
            t_start: 0, t_end: duration, tolerance: 1e-8,
        }, expected: { status: 'computed', final_state: [initial * Math.exp(rate * duration)] } });
    }
    const process = spawnSync('php', ['tests/Support/science-kernel-reference.php'], {
        cwd: fileURLToPath(new URL('../../', import.meta.url)), input: JSON.stringify(generated), encoding: 'utf8', timeout: 30000,
    });
    assert.equal(process.status, 0, process.stderr || process.stdout);
    const php = JSON.parse(process.stdout);
    generated.forEach((fixture, index) => {
        const browser = solveScience(fixture.solver, fixture.inputs);
        assert.equal(browser.status, 'computed', fixture.name);
        assert.equal(php[index].status, 'computed', fixture.name);
        for (const field of ['solution', 'final_state']) {
            fixture.expected[field]?.forEach((expected, i) => {
                close(browser[field][i], expected, field === 'solution' ? 1e-10 : 2e-7);
                close(browser[field][i], php[index][field][i], 1e-11);
            });
        }
        if (fixture.expected.value !== undefined) {
            close(browser.value, fixture.expected.value, fixture.solver === 'root_scalar' ? 1e-9 : 1e-8);
            close(browser.value, php[index].value, 1e-11);
        }
    });
});
