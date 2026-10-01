import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { tokenDisplay } from '../../resources/js/ai/token-usage.js';

const source = readFileSync(new URL('../../resources/js/ai/workspace.js', import.meta.url), 'utf8');
const initialization = source.slice(source.indexOf('function initTokenCounter('));
const turn = { prompt: 200, completion: 20, total: 220, status: 'complete' };
const session = { prompt: 300, completion: 30, total: 330, status: 'complete' };

function harness() {
    const handlers = new Map();
    const controls = new Map(['in', 'out', 'detail-in', 'detail-out', 'detail-total', 'session-total', 'measurement', 'draft-row', 'draft-estimate']
        .map(key => [`[data-ai-token-${key}]`, { textContent: '', hidden: true, classList: { add() {}, remove() {} } }]));
    const counter = { dataset: {}, querySelector: selector => controls.get(selector), addEventListener() {} };
    const root = { querySelector: () => counter };
    const input = { value: '', addEventListener: (event, handler) => handlers.set(event, handler) };
    const context = vm.createContext({ tokenDisplay, document: { addEventListener() {} } });
    vm.runInContext(initialization, context);
    const api = context.initTokenCounter(root, input);
    api.update(turn, session);
    return {
        api, counter, input,
        text: key => controls.get(`[data-ai-token-${key}]`).textContent,
        control: key => controls.get(`[data-ai-token-${key}]`),
        type(value) { input.value = value; handlers.get('input')(); },
    };
}

test('the pill displays accumulated session input and output while details retain the last turn', () => {
    const ui = harness();
    assert.equal(ui.text('in'), '300 in');
    assert.equal(ui.text('out'), '30 out');
    assert.equal(ui.text('detail-in'), '200');
    assert.equal(ui.text('detail-out'), '20');
    assert.equal(ui.text('detail-total'), '220');
    assert.equal(ui.text('session-total'), '330 tok');
    assert.equal(ui.counter.dataset.sessionPrompt, 300);
    assert.equal(ui.counter.dataset.sessionCompletion, 30);
    assert.equal(ui.counter.dataset.sessionStatus, 'complete');
});

test('typing and clearing a draft never replace the accumulated usage', () => {
    const ui = harness();
    ui.type('Pesan baru');
    assert.equal(ui.text('in'), '300 in');
    assert.equal(ui.text('out'), '30 out');
    assert.equal(ui.control('draft-row').hidden, false);
    assert.equal(ui.text('draft-estimate'), '~3 tok');
    ui.type('');
    assert.equal(ui.text('in'), '300 in');
    assert.equal(ui.control('draft-row').hidden, true);
});

test('programmatic draft changes can refresh the separate estimate after submission or retry', () => {
    const ui = harness();
    ui.type('Pesan baru');
    ui.input.value = '';
    ui.api.updateDraft();
    assert.equal(ui.control('draft-row').hidden, true);
    ui.input.value = 'Pesan baru';
    ui.api.updateDraft();
    assert.equal(ui.control('draft-row').hidden, false);
    assert.equal(ui.text('in'), '300 in');
});

test('replaying the same server totals never adds them twice', () => {
    const ui = harness();
    ui.api.update(turn, session);
    ui.api.update(turn, session);
    assert.equal(ui.text('in'), '300 in');
    assert.equal(ui.text('out'), '30 out');
    assert.equal(ui.text('session-total'), '330 tok');
});

test('unknown or partial session coverage governs the pill even when the last turn is complete', () => {
    const ui = harness();
    ui.api.update(turn, { ...session, status: 'partial' });
    assert.equal(ui.text('in'), '≥300 in');
    assert.equal(ui.text('out'), '≥30 out');
    assert.equal(ui.text('session-total'), '≥330 tok');
    assert.equal(ui.text('measurement'), 'Tercatat');
    ui.api.update(turn, { prompt: 0, completion: 0, total: 0, status: 'unknown' });
    assert.equal(ui.text('in'), '— in');
    assert.equal(ui.text('out'), '— out');
    assert.equal(ui.text('detail-total'), '220');
});

test('session-only updates work and turn-only updates preserve the last authoritative session totals', () => {
    const ui = harness();
    ui.api.update(null, { prompt: 500, completion: 50, total: 550, status: 'complete' });
    assert.equal(ui.text('in'), '500 in');
    assert.equal(ui.text('out'), '50 out');
    assert.equal(ui.text('detail-total'), '220');
    ui.api.update({ prompt: 100, completion: 10, total: 110, status: 'complete' });
    assert.equal(ui.text('in'), '500 in');
    assert.equal(ui.text('out'), '50 out');
    assert.equal(ui.text('detail-total'), '110');
});
