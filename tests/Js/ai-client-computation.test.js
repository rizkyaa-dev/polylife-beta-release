import test from 'node:test';
import assert from 'node:assert/strict';
import { executeClientProgram } from '../../resources/js/ai/science/client/engine.js';
import { validateResult } from '../../resources/js/ai/science/client/contract.js';
import { ScienceCoordinator } from '../../resources/js/ai/science/coordinator.js';

const program = source => ({ version: 'client-js-1', hash: 'a'.repeat(64), source, inputs: { D: 0.08, L: 6 }, checks: ['geometry identity'] });

test('session 349 arithmetic executes with exact rational cross-check', async () => {
    const result = await executeClientProgram({ ...program(`function compute(){
        var value=78*554/54-988*128286/12;
        return {values:{value:value},checks:[{name:'rational identity',passed:Math.abs(value*9+95052724)<1e-6,residual:value*9+95052724}]};
    }`), output_names: ['value'] });
    assert.equal(result.values.value, -10561413.777777778);
    assert.equal(result.checks[0].passed, true);
});

test('guest JS computes geometry with an independent identity check', async () => {
    const result = await executeClientProgram(program('function compute(i){const A=Math.PI*i.D*i.D/4; const V=A*i.L;return {values:{A,V},checks:[{name:"V/A=L",passed:Math.abs(V/A-i.L)<1e-12,residual:Math.abs(V/A-i.L)}]}}'));
    assert.ok(Math.abs(result.values.A - 0.005026548245743669) < 1e-15);
    assert.ok(result.checks[0].passed);
});

test('guest has no DOM, network, worker, module or host process bindings', async () => {
    const result = await executeClientProgram(program('function compute(){return {values:{bindings:[typeof fetch,typeof XMLHttpRequest,typeof document,typeof window,typeof self,typeof Worker,typeof localStorage,typeof process,typeof require]},checks:[]}}'));
    assert.deepEqual(result.values.bindings, Array(9).fill('undefined'));
});

test('constructor and eval remain inside the guest VM', async () => {
    const result = await executeClientProgram(program('function compute(){return {values:{host:({}).constructor.constructor("return typeof fetch")(),eval:eval("typeof document")},checks:[]}}'));
    assert.equal(result.values.host, 'undefined');
    assert.equal(result.values.eval, 'undefined');
});

test('infinite loops are interrupted and subsequent executions still work', async () => {
    await assert.rejects(executeClientProgram(program('while(true){}'), { computeMs: 10 }), error => error.code === 'timeout');
    const result = await executeClientProgram(program('function compute(){return {values:{ok:1},checks:[]}}'));
    assert.equal(result.values.ok, 1);
});

test('excessive guest allocation fails within its bounded heap', async () => {
    await assert.rejects(executeClientProgram(program('function compute(){let a=[];while(true)a.push(new Array(100000).fill(1));}')));
});

test('syntax errors, promises, nonfinite and excessive outputs are rejected', async () => {
    for (const source of ['function compute(', 'async function compute(){return {values:{x:1},checks:[]}}', 'function compute(){return {values:{x:NaN},checks:[]}}', 'function compute(){return {values:{x:"a".repeat(20000)},checks:[]}}']) {
        await assert.rejects(executeClientProgram(program(source)));
    }
});

test('invalid generated property names produce syntax_error, not an opaque runtime failure', async () => {
    await assert.rejects(executeClientProgram(program('function compute(){return {values:{numerical_V_at_0.2_s:1},checks:[]}}')),
        error => error.code === 'syntax_error');
});

test('requested output names are enforced in the actual guest engine', async () => {
    const source = 'function compute(){return {values:{renamed:3},checks:[{name:"inverse",passed:true}]}}';
    await assert.rejects(executeClientProgram({ ...program(source), output_names: ['value'] }));
    const result = await executeClientProgram({ ...program(source.replace('renamed:', 'value:')), output_names: ['value'] });
    assert.equal(result.values.value, 3);
});

test('verification claims are stripped from script results', () => {
    const result = validateResult({ values: { x: 1 }, checks: [], verification: 'physically_verified', authoritative_runner: 'server' });
    assert.deepEqual(result, { values: { x: 1 }, checks: [] });
});

test('client fallback is denied by default and never invokes the kernel runner', async () => {
    const submitted = [];
    const coordinator = new ScienceCoordinator({ origin: 'http://localhost', clientId: 'id',
        post: async (url, body) => {
            if (url.endsWith('claim')) return { id: 1, attempt: 1, token: 't'.repeat(48), submit_url: 'http://localhost/submit', execution_mode: 'client_script', program: program('function compute(){}') };
            submitted.push(body);
        }, runnerFactory: () => { throw new Error('Kernel fallback must never execute guest JS.'); } });
    await coordinator.process({ id: 1, claim_url: 'http://localhost/claim' }, new AbortController().signal);
    assert.equal(submitted[0].failure, 'cancelled');
});

test('approved dynamic result is submitted once and never cached as a kernel result', async () => {
    let computations = 0;
    let submits = 0;
    const coordinator = new ScienceCoordinator({ origin: 'http://localhost', clientId: 'id', requestApproval: async () => true,
        clientRunnerFactory: async () => ({ run: async () => { computations++; return { values: { x: 1 }, checks: [] }; } }),
        cacheFactory: () => { throw new Error('Dynamic results must not enter the numerical cache.'); },
        post: async url => {
            if (url.endsWith('claim')) return { id: 1, attempt: 1, token: 't'.repeat(48), submit_url: 'http://localhost/submit', execution_mode: 'client_script', program: program('function compute(){}') };
            if (++submits === 1) throw new Error('Response lost');
        } });
    const offer = { id: 1, claim_url: 'http://localhost/claim' };
    await assert.rejects(coordinator.process(offer, new AbortController().signal));
    await coordinator.process(offer, new AbortController().signal);
    assert.equal(computations, 1);
    assert.equal(submits, 2);
});

test('zero-click executes the actual guest engine and retries callback without approval or kernel', async () => {
    let executions = 0;
    let submissions = 0;
    const coordinator = new ScienceCoordinator({ origin: 'http://localhost', clientId: 'id', kernelEnabled: false, autoExecute: true,
        requestApproval: () => { throw new Error('No user click is allowed.'); },
        runnerFactory: () => { throw new Error('Kernel is disabled.'); },
        cacheFactory: () => { throw new Error('Kernel cache must not load.'); },
        clientRunnerFactory: async () => ({ run: async p => { executions++; return executeClientProgram(p); } }),
        post: async url => {
            if (url.endsWith('/claim')) return { id: 1, attempt: 1, token: 't'.repeat(48), auto_execute: true,
                submit_url: 'http://localhost/submit', execution_mode: 'client_script',
                program: { ...program('function compute(i){return {values:{value:i.D*i.L},checks:[{name:"inverse",passed:true}]}}'), output_names: ['value'] } };
            if (++submissions === 1) throw new Error('Callback response lost.');
        } });
    const offer = { id: 1, claim_url: 'http://localhost/claim' };
    await assert.rejects(coordinator.process(offer, new AbortController().signal));
    await coordinator.process(offer, new AbortController().signal);
    assert.equal(executions, 1);
    assert.equal(submissions, 2);
});

test('server can disable automatic execution even when the page enables it', async () => {
    let approvals = 0;
    const submitted = [];
    const coordinator = new ScienceCoordinator({ origin: 'http://localhost', clientId: 'id', autoExecute: true,
        requestApproval: async () => { approvals++; return false; },
        clientRunnerFactory: () => { throw new Error('Rejected script must not run.'); },
        post: async (url, body) => {
            if (url.endsWith('/claim')) return { id: 1, attempt: 1, token: 't'.repeat(48), auto_execute: false,
                submit_url: 'http://localhost/submit', execution_mode: 'client_script', program: program('function compute(){}') };
            submitted.push(body);
        } });
    await coordinator.process({ id: 1, claim_url: 'http://localhost/claim' }, new AbortController().signal);
    assert.equal(approvals, 1);
    assert.equal(submitted[0].failure, 'cancelled');
});
