import test from 'node:test';
import assert from 'node:assert/strict';
import { ClientComputationRunner } from '../../resources/js/ai/science/client/runner.js';
import { validateProgram } from '../../resources/js/ai/science/client/contract.js';

const program = { version: 'client-js-1', hash: 'a'.repeat(64), source: 'function compute(){}', inputs: {}, checks: ['bounded check'] };

function fakeWorkers(context, throws = false) {
    const previous = globalThis.Worker;
    const workers = [];
    globalThis.Worker = class {
        constructor() { workers.push(this); }
        terminate() { this.terminated = true; }
        postMessage() { if (throws) throw new Error('Clone failed.'); }
    };
    context.after(() => { globalThis.Worker = previous; });
    return workers;
}

test('postMessage failure releases the worker and permits retry', async context => {
    const workers = fakeWorkers(context, true);
    const runner = new ClientComputationRunner();
    await assert.rejects(runner.run(program), error => error.code === 'execution_failed');
    assert.equal(workers[0].terminated, true);
    assert.equal(runner.active, null);
    await assert.rejects(runner.run(program), error => error.code === 'execution_failed');
    assert.equal(workers.length, 2);
});

test('malformed worker message rejects and cleans up instead of hanging', async context => {
    const workers = fakeWorkers(context);
    const runner = new ClientComputationRunner();
    const result = runner.run(program);
    workers[0].onmessage({ data: null });
    await assert.rejects(result, error => error.code === 'invalid_input');
    assert.equal(workers[0].terminated, true);
    assert.equal(runner.active, null);
});

test('late callback from a finished worker cannot clear a new active computation', async context => {
    const workers = fakeWorkers(context);
    const runner = new ClientComputationRunner();
    const first = runner.run(program);
    const late = workers[0].onmessage;
    late({ data: { ready: true } });
    late({ data: { result: { values: { x: 1 }, checks: [] } } });
    await first;
    const second = runner.run(program);
    const active = runner.active;
    late({ data: { failure: 'timeout' } });
    assert.equal(runner.active, active);
    runner.dispose();
    await assert.rejects(second, error => error.code === 'cancelled');
    assert.equal(workers[1].terminated, true);
});

test('startup failure retries once before sending any guest code', async context => {
    const workers = fakeWorkers(context);
    const messages = [];
    const runner = new ClientComputationRunner();
    const result = runner.run(program);
    workers[0].onerror();
    assert.equal(workers[0].terminated, true);
    assert.equal(workers.length, 2);
    workers[1].postMessage = data => messages.push(data);
    workers[1].onmessage({ data: { ready: true } });
    assert.deepEqual(messages, [{ program }]);
    workers[1].onmessage({ data: { result: { values: { x: 1 }, checks: [] } } });
    assert.equal((await result).values.x, 1);
});

test('persistent startup failure is unavailable and never loops', async context => {
    const workers = fakeWorkers(context);
    const runner = new ClientComputationRunner();
    const result = runner.run(program);
    workers[0].onmessage({ data: { failure: 'unavailable' } });
    workers[1].onerror();
    await assert.rejects(result, error => error.code === 'unavailable');
    assert.equal(workers.length, 2);
    assert.equal(runner.active, null);
});

test('abort during worker construction does not send guest code or initialize runtime', async context => {
    const previous = globalThis.Worker;
    const controller = new AbortController();
    let terminated = false;
    globalThis.Worker = class {
        constructor() { controller.abort(); }
        terminate() { terminated = true; }
        postMessage() { assert.fail('Aborted worker must not receive messages'); }
    };
    context.after(() => { globalThis.Worker = previous; });
    const runner = new ClientComputationRunner();
    await assert.rejects(runner.run(program, { signal: controller.signal }), error => error.code === 'cancelled');
    assert.equal(terminated, true);
    assert.equal(runner.active, null);
});

test('failure after guest starts never repeats computation', async context => {
    const workers = fakeWorkers(context);
    const runner = new ClientComputationRunner();
    const result = runner.run(program);
    workers[0].onmessage({ data: { ready: true } });
    workers[0].onerror();
    await assert.rejects(result, error => error.code === 'execution_failed');
    assert.equal(workers.length, 1);
});

test('planned check envelope is validated before approval rendering', () => {
    for (const checks of [undefined, [], [null], [''], Array(9).fill('check')]) {
        assert.throws(() => validateProgram({ ...program, checks }));
    }
});
