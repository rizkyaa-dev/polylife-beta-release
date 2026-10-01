import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { PendingAiTurns } from '../../resources/js/ai/pending-turns.js';
import { PendingAiTurn, isDefinitiveFailure, terminalRunError } from '../../resources/js/ai/turn-recovery.js';

const source = readFileSync(new URL('../../resources/js/ai/workspace.js', import.meta.url), 'utf8');
const interactions = source.slice(source.indexOf('function initMessageInteractions('), source.indexOf('\nasync function activateBranch('));
const stopHandler = source.slice(source.indexOf("    send.addEventListener('click'"), source.indexOf("    input.addEventListener('input'"));

function editHarness({ outcome = 'cancelled', acceptanceFailure = false } = {}) {
    const handlers = new Map();
    const pendingTurns = new PendingAiTurns();
    const input = { value: 'Edit awal', focus() {}, setSelectionRange() {} };
    const error = { hidden: true, textContent: '' };
    const text = { textContent: 'Original text' };
    const actions = {};
    const message = { dataset: { messageId: '42' }, querySelector(selector) {
        if (selector === '[data-ai-edit-form]') return form;
        if (selector === '[data-message-text]') return text;
        if (selector === '[data-ai-message-actions]') return actions;
        return null;
    } };
    const form = {
        closest: () => message,
        querySelector: selector => selector === '[data-ai-edit-input]' ? input : error,
        querySelectorAll: () => [],
        setAttribute() {},
    };
    const root = {
        dataset: { editUrlTemplate: '/messages/__MESSAGE__', cancelRunUrlTemplate: '/runs/__RUN__/cancel', workspaceUrl: 'http://localhost/ai' },
        addEventListener(type, listener) { const items = handlers.get(type) ?? []; items.push(listener); handlers.set(type, items); },
        dispatchEvent(event) { for (const listener of handlers.get(event.type) ?? []) listener(event); },
    };
    const payloads = [];
    const cancellations = [];
    let stop;
    let observationError = new TypeError('offline');
    let requestIds = 0;
    const context = vm.createContext({
        busy: false, stopping: false, pendingTurns, root, PendingAiTurn, isDefinitiveFailure, URL,
        createRequestId: () => `request-${++requestIds}`,
        send: { addEventListener: (_, handler) => { stop = handler; } },
        resize() {}, hideError() {}, showError() {},
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        postJson: async url => { cancellations.push(url); return { run_status: outcome }; },
        sendJson: async (_, __, payload) => {
            payloads.push(payload);
            if (acceptanceFailure && payloads.length === 1) throw new TypeError('acceptance response lost');
            return { run_id: 6 + payloads.length, session_id: 3 };
        },
        waitForRun: async () => { throw observationError; },
        window: { location: { assign() {} } },
    });
    const state = {
        pendingTurns,
        isBusy: owner => context.busy || context.stopping || pendingTurns.isBlocked(owner),
        setBusy: value => { context.busy = value; },
        refresh() {},
    };
    vm.runInContext(interactions + '\n' + stopHandler, context);
    context.initMessageInteractions(root, state);
    return {
        input, error, form, pendingTurns, payloads, cancellations, state,
        submit: () => handlers.get('submit')[0]({ target: { closest: () => form }, preventDefault() {} }),
        stop: () => stop({ preventDefault() {} }),
        reopen: () => handlers.get('click')[0]({ target: { closest: selector => selector === '.ai-message-user' ? message : selector === '[data-ai-edit-open]' ? {} : null } }),
        set observationError(value) { observationError = value; },
    };
}

test('Stop clears accepted edit after polling disconnects and allows a different draft', async () => {
    const ui = editHarness();
    await ui.submit();
    assert.equal(ui.pendingTurns.activeRunId, 7);
    assert.equal(ui.state.isBusy(), true);
    assert.equal(ui.state.isBusy(ui.form), false);
    await ui.stop();
    assert.deepEqual(ui.cancellations, ['/runs/7/cancel']);
    assert.equal(ui.pendingTurns.activeRunId, null);
    assert.equal(ui.error.hidden, true);
    assert.equal(ui.input.value, 'Edit awal');
    ui.input.value = 'Edit baru';
    await ui.submit();
    assert.equal(ui.payloads.length, 2);
    assert.equal(ui.payloads[1].message, 'Edit baru');
    assert.notEqual(ui.payloads[0].request_id, ui.payloads[1].request_id);
});

test('completion winning the Stop race retains the accepted turn for reconciliation', async () => {
    const ui = editHarness({ outcome: 'completed' });
    await ui.submit();
    await ui.stop();
    assert.equal(ui.pendingTurns.activeRunId, 7);
    await ui.submit();
    assert.equal(ui.payloads.length, 1);
});

test('Stop can release an already failed run without trapping the next edit', async () => {
    const ui = editHarness({ outcome: 'failed' });
    await ui.submit();
    await ui.stop();
    assert.equal(ui.pendingTurns.activeRunId, null);
    assert.equal(ui.pendingTurns.isBlocked(), false);
});

test('retrying accepted edit polls the same run without another PATCH and terminal failure releases it', async () => {
    const ui = editHarness();
    await ui.submit();
    await ui.submit();
    assert.equal(ui.payloads.length, 1);
    ui.observationError = terminalRunError('cancelled', true);
    await ui.submit();
    assert.equal(ui.pendingTurns.activeRunId, null);
    ui.input.value = 'Fresh edit';
    await ui.submit();
    assert.equal(ui.payloads.length, 2);
});

test('lost edit acceptance keeps identity, blocks other turns and preserves the draft on reopening', async () => {
    const ui = editHarness({ acceptanceFailure: true });
    await ui.submit();
    assert.equal(ui.pendingTurns.activeRunId, null);
    assert.equal(ui.state.isBusy(), true);
    assert.equal(ui.pendingTurns.isBlocked({}), true);
    await ui.reopen();
    assert.equal(ui.input.value, 'Edit awal');
    await ui.submit();
    assert.equal(ui.payloads.length, 2);
    assert.equal(ui.payloads[0].request_id, ui.payloads[1].request_id);
});

test('cancellation targets its run and cannot discard a different pending turn', () => {
    const store = new PendingAiTurns();
    const owner = {};
    const turn = new PendingAiTurn('id', 'prompt', 3, { run_id: 7, session_id: 3 });
    store.set(owner, turn);
    assert.deepEqual(store.cancel(8), []);
    assert.equal(store.get(owner), turn);
    assert.throws(() => store.set({}, new PendingAiTurn('other', 'new', 3)));
    store.cancel(7);
    assert.equal(store.isBlocked(), false);
});
