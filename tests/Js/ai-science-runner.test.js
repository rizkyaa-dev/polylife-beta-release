import assert from 'node:assert/strict';
import test from 'node:test';
import { ScienceRunner } from '../../resources/js/ai/science/runner.js';
import { SCIENCE_KERNEL_VERSION } from '../../resources/js/ai/science/contract.js';

class FakeWorker extends EventTarget {
    terminated = false;
    postMessage(data) { this.request = data; }
    terminate() { this.terminated = true; }
    reply(data) { const event = new Event('message'); event.data = data; this.dispatchEvent(event); }
    succeed() { this.reply({ id: this.request.id, kernel_version: SCIENCE_KERNEL_VERSION,
        result: { status: 'computed', solution: [2], kernel_version: SCIENCE_KERNEL_VERSION } }); }
}

function harness() {
    const workers = [];
    const timers = new Map();
    let time = 0;
    const runner = new ScienceRunner({ workerFactory: () => { const worker = new FakeWorker(); workers.push(worker); return worker; },
        now: () => time, setTimer: callback => { const id = timers.size + 1; timers.set(id, callback); return id; }, clearTimer: id => timers.delete(id) });
    return { runner, workers, timers, advance: value => { time = value; } };
}
const inputs = { matrix: [[2]], rhs: [4] };
const errorCode = code => error => error.code === code;

test('runner returns bounded worker result and cleans up all resources', async () => {
    const { runner, workers, timers } = harness();
    const promise = runner.run('linear_system', inputs);
    workers[0].succeed();
    assert.deepEqual((await promise).solution, [2]);
    assert.ok(workers[0].terminated);
    assert.equal(timers.size, 0);
    assert.equal(runner.active, null);
});

test('busy requests do not create an unbounded queue; cancellation permits a new attempt', async () => {
    const { runner, workers } = harness();
    const controller = new AbortController();
    const first = runner.run('linear_system', inputs, { signal: controller.signal });
    await assert.rejects(runner.run('linear_system', inputs), errorCode('busy'));
    controller.abort();
    await assert.rejects(first, errorCode('cancelled'));
    const second = runner.run('linear_system', inputs);
    workers[0].succeed();
    assert.ok(runner.active);
    workers[1].succeed();
    await second;
    assert.equal(workers.length, 2);
});

test('timed-out and late results are rejected even if timer is throttled', async () => {
    const { runner, workers, timers, advance } = harness();
    const first = runner.run('linear_system', inputs, { budgetMs: 10 });
    advance(11);
    workers[0].succeed();
    await assert.rejects(first, errorCode('timeout'));
    assert.equal(timers.size, 0);
    const second = runner.run('linear_system', inputs, { budgetMs: 10 });
    [...timers.values()][0]();
    await assert.rejects(second, errorCode('timeout'));
    assert.ok(workers[1].terminated);
});

test('dispose, unsupported browser, pre-abort and invalid budgets fail explicitly', async () => {
    const { runner, workers } = harness();
    const controller = new AbortController(); controller.abort();
    await assert.rejects(runner.run('linear_system', inputs, { signal: controller.signal }), errorCode('cancelled'));
    await assert.rejects(runner.run('linear_system', inputs, { budgetMs: Infinity }), errorCode('invalid_input'));
    await assert.rejects(runner.run('linear_system', { matrix: [[1]], rhs: ['2'] }), errorCode('invalid_input'));
    assert.equal(workers.length, 0);
    const promise = runner.run('linear_system', inputs);
    runner.dispose();
    await assert.rejects(promise, errorCode('cancelled'));
    await assert.rejects(runner.run('linear_system', inputs), errorCode('disposed'));
    const missing = new ScienceRunner({ workerFactory: () => { throw new Error('CSP'); } });
    await assert.rejects(missing.run('linear_system', inputs), errorCode('unavailable'));
});

test('stale IDs ignored; version skew and worker errors fail without retrying', async () => {
    const { runner, workers } = harness();
    const first = runner.run('linear_system', inputs);
    workers[0].reply({ id: 'different', kernel_version: SCIENCE_KERNEL_VERSION });
    assert.ok(runner.active);
    workers[0].reply({ id: workers[0].request.id, kernel_version: 'old' });
    await assert.rejects(first, errorCode('version_mismatch'));
    const second = runner.run('linear_system', inputs);
    workers[1].dispatchEvent(new Event('error'));
    await assert.rejects(second, errorCode('execution_failed'));
    assert.equal(workers.length, 2);
});

test('abort during worker construction is observed before posting input', async () => {
    const controller = new AbortController();
    const worker = new FakeWorker();
    const runner = new ScienceRunner({ workerFactory: () => { controller.abort(); return worker; } });
    await assert.rejects(runner.run('linear_system', inputs, { signal: controller.signal }), errorCode('cancelled'));
    assert.ok(worker.terminated);
    assert.equal(worker.request, undefined);
});
