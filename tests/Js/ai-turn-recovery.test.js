import test from 'node:test';
import assert from 'node:assert/strict';
import { PendingAiTurn, isDefinitiveFailure, terminalRunError } from '../../resources/js/ai/turn-recovery.js';
import { observeAiRun } from '../../resources/js/ai/run-observer.js';

test('lost acceptance response replays the original request identity and context', async () => {
    const turn = new PendingAiTurn('request-1', 'buat portfolio', null);
    const payloads = [];
    const send = async payload => {
        payloads.push(payload);
        if (payloads.length === 1) throw new TypeError('offline after server accepted');
        return { run_id: 7, session_id: 3 };
    };
    await assert.rejects(turn.reconcile(send));
    assert.equal(turn.accepted, null);
    await turn.reconcile(send);
    assert.deepEqual(payloads[0], payloads[1]);
    assert.equal(turn.accepted.run_id, 7);
});

test('polling failure after acceptance never sends a second POST', async () => {
    const turn = new PendingAiTurn('request-1', 'buat portfolio', 3);
    let calls = 0;
    const send = async () => { calls++; return { run_id: 7, session_id: 3 }; };
    await turn.reconcile(send);
    await turn.reconcile(send);
    await turn.reconcile(send);
    assert.equal(calls, 1);
});

test('page-resumed run can recover without any POST', async () => {
    const turn = new PendingAiTurn(null, 'buat portfolio', 3, { run_id: 7, session_id: 3 });
    assert.equal((await turn.reconcile(() => assert.fail('must not POST'))).run_id, 7);
});

test('invalid acceptance does not erase identity or cache invalid run data', async () => {
    const turn = new PendingAiTurn('same-id', 'buat portfolio', 3);
    await assert.rejects(turn.reconcile(async () => null));
    assert.equal(turn.accepted, null);
    assert.equal(turn.payload().request_id, 'same-id');
});

test('network, request timeout and server errors are uncertain, not run failure', () => {
    for (const error of [new TypeError('offline'), new Error('invalid JSON'), Object.assign(new Error(), { httpStatus: 408 }), Object.assign(new Error(), { httpStatus: 500 })]) {
        assert.equal(isDefinitiveFailure(error), false);
    }
    assert.equal(isDefinitiveFailure(terminalRunError('provider failed')), true);
    assert.equal(isDefinitiveFailure(terminalRunError('cancelled', true)), true);
    assert.equal(isDefinitiveFailure(Object.assign(new Error(), { httpStatus: 422 })), true);
    assert.equal(isDefinitiveFailure(Object.assign(new Error(), { httpStatus: 401 }), true), false);
    assert.equal(isDefinitiveFailure(Object.assign(new Error(), { httpStatus: 429 })), false);
});

test('observer reconnects to same run after transient status failure', async () => {
    const urls = [];
    const progress = [];
    const result = await observeAiRun('/runs/7', {
        read: async url => {
            urls.push(url);
            if (urls.length === 1) throw new TypeError('offline');
            if (urls.length === 2) return { status: 'running', phase: 'processing' };
            return { status: 'success', reply: 'complete' };
        }, sleep: async () => {}, onProgress: data => progress.push(data),
    });
    assert.equal(result.reply, 'complete');
    assert.deepEqual(urls, ['/runs/7', '/runs/7', '/runs/7']);
    assert.equal(progress.length, 1);
});

test('observer stops boundedly when offline, without marking run failed', async () => {
    let calls = 0;
    await assert.rejects(observeAiRun('/runs/7', {
        read: async () => { calls++; throw new TypeError('offline'); }, sleep: async () => {},
    }), error => !error.terminalRun);
    assert.equal(calls, 3);
});

test('observer identifies only explicit failed or cancelled states as terminal', async () => {
    for (const status of ['failed', 'cancelled']) {
        await assert.rejects(observeAiRun('/runs/7', {
            read: async () => ({ status }), sleep: async () => {},
        }), error => error.terminalRun === true);
    }
});

test('authentication rejection after ambiguous acceptance does not erase original identity', async () => {
    const turn = new PendingAiTurn('original-id', 'buat portfolio', null);
    await assert.rejects(turn.reconcile(async () => { throw new TypeError('response lost'); }));
    await assert.rejects(turn.reconcile(async () => { throw Object.assign(new Error('login expired'), { httpStatus: 401 }); }));
    assert.equal(turn.uncertain, true);
    assert.equal(turn.payload().request_id, 'original-id');
    assert.equal(isDefinitiveFailure({ httpStatus: 401 }, turn.uncertain), false);
});
