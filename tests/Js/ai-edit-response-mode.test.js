import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {PendingAiTurn, isDefinitiveFailure} from '../../resources/js/ai/turn-recovery.js';
import {PendingAiTurns} from '../../resources/js/ai/pending-turns.js';

function editHarness(mode, failFirst = false) {
    const listeners = new Map();
    const requests = [];
    const input = {value: 'Revised prompt', focus() {}};
    const error = {};
    const form = {
        closest: () => ({dataset: {messageId: '42'}}),
        querySelector: selector => selector === '[data-ai-edit-input]' ? input : error,
        querySelectorAll: () => [], setAttribute() {},
    };
    const root = {
        dataset: {aiResponseMode: mode, editUrlTemplate: '/ai/messages/__MESSAGE__', workspaceUrl: 'http://localhost/ai'},
        addEventListener: (name, handler) => listeners.set(name, handler),
    };
    const state = {pendingTurns: new PendingAiTurns(), isBusy: () => false, setBusy() {}, refresh() {}, onRunStatus() {}};
    const source = readFileSync(new URL('../../resources/js/ai/workspace.js', import.meta.url), 'utf8');
    const fn = source.slice(source.indexOf('function initMessageInteractions('), source.indexOf('\nfunction setEditMode('));
    const context = vm.createContext({
        root, state, PendingAiTurn, isDefinitiveFailure, URL,
        createRequestId: () => 'original-id',
        window: {location: {assign() {}}},
        sendJson: async (url, method, payload) => {
            requests.push({url, method, payload});
            if (failFirst && requests.length === 1) throw new TypeError('Response lost');
            return {run_id: 7, session_id: 3};
        },
        waitForRun: async () => ({session_id: 3}),
    });
    vm.runInContext(fn + '\ninitMessageInteractions(root, state);', context);
    return {root, requests, submit: () => listeners.get('submit')({target: {closest: () => form}, preventDefault() {}})};
}

for (const mode of ['default', 'eco']) {
    test(`actual edit submission preserves ${mode} mode`, async () => {
        const ui = editHarness(mode);
        await ui.submit();
        assert.equal(ui.requests[0].method, 'PATCH');
        assert.equal(ui.requests[0].url, '/ai/messages/42');
        assert.equal(ui.requests[0].payload.response_mode, mode === 'eco' ? 'eco' : undefined);
        assert.equal(ui.requests[0].payload.message, 'Revised prompt');
    });
}

test('changing the UI mode after an uncertain edit does not change its replay payload', async () => {
    const ui = editHarness('eco', true);
    await ui.submit();
    ui.root.dataset.aiResponseMode = 'default';
    await ui.submit();
    assert.deepEqual(ui.requests[0].payload, ui.requests[1].payload);
    assert.equal(ui.requests[1].payload.response_mode, 'eco');
});
