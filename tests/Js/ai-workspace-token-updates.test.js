import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { PendingAiTurns } from '../../resources/js/ai/pending-turns.js';
import { PendingAiTurn, isDefinitiveFailure } from '../../resources/js/ai/turn-recovery.js';
import { tokenDisplay } from '../../resources/js/ai/token-usage.js';
import { observeAiRun } from '../../resources/js/ai/run-observer.js';

const source = readFileSync(new URL('../../resources/js/ai/workspace.js', import.meta.url), 'utf8');
const interactions = source.slice(source.indexOf('function initMessageInteractions('), source.indexOf('\nasync function activateBranch('));
const tokenCounter = source.slice(source.indexOf('function initTokenCounter('));
const observation = source.slice(source.indexOf('async function waitForRun('), source.indexOf('\nfunction initSettingsEffortSlider('));
const editorSetup = source.slice(source.indexOf('    initMessageInteractions(root, {'), source.indexOf('    initEarlierMessages('));

function editHarness(status) {
    const handlers = new Map();
    const controls = new Map(['in', 'out', 'detail-in', 'detail-out', 'detail-total', 'session-total', 'measurement']
        .map(key => [`[data-ai-token-${key}]`, { textContent: '', classList: { add() {}, remove() {} } }]));
    const counter = { dataset: {}, querySelector: selector => controls.get(selector), addEventListener() {} };
    const input = { value: 'Revisi pertanyaan', focus() {} };
    const error = { hidden: true, textContent: '' };
    const message = { dataset: { messageId: '42' } };
    const form = {
        closest: () => message,
        querySelector: selector => selector === '[data-ai-edit-input]' ? input : error,
        querySelectorAll: () => [], setAttribute() {},
    };
    const root = {
        dataset: { editUrlTemplate: '/messages/__MESSAGE__', runUrlTemplate: '/runs/__RUN__', workspaceUrl: 'http://localhost/ai' },
        querySelector: selector => selector === '[data-ai-token-counter]' ? counter : null,
        addEventListener: (type, handler) => handlers.set(type, handler),
        removeEventListener: type => handlers.delete(type),
    };
    const statuses = [
        { status: 'running', run: { tokens: { prompt: 150, completion: 10, total: 160, status: 'partial' } }, session_tokens: { prompt: 250, completion: 20, total: 270, status: 'partial' } },
        { status, session_id: 3, run: { tokens: { prompt: 200, completion: 20, total: 220, status: 'complete' } }, session_tokens: { prompt: 300, completion: 30, total: 330, status: 'complete' } },
    ];
    const requests = [];
    const observations = [];
    const navigations = [];
    const pendingTurns = new PendingAiTurns();
    const context = vm.createContext({
        root, input: null, pendingTurns, tokenDisplay, PendingAiTurn, isDefinitiveFailure, URL,
        busy: false, stopping: false, sessionId: 3, resize() {},
        addEventListener() {}, removeEventListener() {},
        ScienceCoordinator: class { dispose() {} observe() {} },
        document: { addEventListener() {} },
        window: { location: { assign: url => navigations.push(String(url)) } },
        createRequestId: () => 'edit-request-id',
        sendJson: async (...args) => { requests.push(args); return { run_id: 7, session_id: 3 }; },
        observeAiRun: (url, options) => observeAiRun(url, {
            ...options, sleep: async () => {},
            read: async () => {
                observations.push(url);
                return statuses.length > 1 ? statuses.shift() : statuses[0];
            },
        }),
    });
    vm.runInContext(interactions + '\n' + tokenCounter + '\n' + observation, context);
    // Use the production callback and editor state wiring, not a test-only callback.
    const callback = source.slice(source.indexOf('    const tokenCounter = initTokenCounter('), source.indexOf('    let sessionId ='));
    vm.runInContext(callback, context);
    vm.runInContext('tokenCounter.update({ prompt: 100, completion: 10, total: 110, status: "complete" }, { prompt: 100, completion: 10, total: 110, status: "complete" });\n' + editorSetup, context);
    return {
        input, error, counter, controls, requests, observations, navigations, pendingTurns,
        submit: () => handlers.get('submit')({ target: { closest: () => form }, preventDefault() {} }),
    };
}

for (const status of ['failed', 'cancelled']) {
    test(`edited message ${status} updates token totals before reporting its terminal state`, async () => {
        const ui = editHarness(status);
        await ui.submit();
        assert.equal(ui.counter.dataset.latestPrompt, 200);
        assert.equal(ui.controls.get('[data-ai-token-in]').textContent, '300 in');
        assert.equal(ui.controls.get('[data-ai-token-out]').textContent, '30 out');
        assert.equal(ui.controls.get('[data-ai-token-detail-total]').textContent, '220');
        assert.equal(ui.controls.get('[data-ai-token-session-total]').textContent, '330 tok');
        assert.equal(ui.controls.get('[data-ai-token-measurement]').textContent, 'Tercatat');
        assert.equal(ui.requests.length, 1);
        assert.deepEqual(ui.observations, ['/runs/7', '/runs/7']);
        assert.equal(ui.navigations.length, 0);
        assert.equal(ui.input.value, 'Revisi pertanyaan');
        assert.equal(ui.pendingTurns.activeRunId, null);
        assert.equal(ui.error.hidden, status === 'cancelled');
    });
}

test('successful edit updates usage before navigating to its session', async () => {
    const ui = editHarness('success');
    await ui.submit();
    assert.equal(ui.controls.get('[data-ai-token-detail-total]').textContent, '220');
    assert.equal(ui.controls.get('[data-ai-token-session-total]').textContent, '330 tok');
    assert.deepEqual(ui.navigations, ['http://localhost/ai?session=3']);
});
