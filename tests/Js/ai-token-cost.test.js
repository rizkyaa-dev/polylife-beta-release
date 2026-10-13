import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { costDisplay, estimateTokenCost, initTokenCost } from '../../resources/js/ai/token-cost.js';
import { tokenDisplay } from '../../resources/js/ai/token-usage.js';

const profile = { input_usd_per_million: 0.30, output_usd_per_million: 1.20, usd_to_idr: 17937 };
const turn = { prompt: 12918, completion: 173, total: 13091, status: 'complete' };

test('peak cache-miss input and output are priced separately in rupiah', () => {
    assert.equal(costDisplay(turn, profile).text, '≈Rp73,24');
    assert.equal(costDisplay(turn, profile, 'input').text, '≈Rp69,51');
    assert.equal(costDisplay(turn, profile, 'output').text, '≈Rp3,72');
    assert.equal(costDisplay({ prompt: 1_000_000, completion: 0, status: 'complete' }, profile).text, '≈Rp5.381,10');
    assert.equal(costDisplay({ prompt: 0, completion: 1_000_000, status: 'complete' }, profile).text, '≈Rp21.524,40');
    assert.equal(costDisplay(turn, { ...profile, usd_to_idr: 20000 }).text, '≈Rp81,66');
});

test('reasoning and cached tokens never add charges to the conservative reference estimate', () => {
    assert.equal(estimateTokenCost({ ...turn, reasoning: 100, cached: 10000 }, profile), estimateTokenCost(turn, profile));
});

test('unknown measurements differ from measured zero and partial estimates remain labelled', () => {
    assert.equal(costDisplay({ ...turn, status: 'unknown' }, profile).text, '—');
    assert.equal(costDisplay({ prompt: 0, completion: 0, status: 'complete' }, profile).text, '≈Rp0,00');
    assert.equal(costDisplay({ ...turn, status: 'partial' }, profile).text, '≈Rp73,24*');
    assert.match(costDisplay({ ...turn, status: 'partial' }, profile).label, /sebagian/);
    assert.equal(costDisplay({ prompt: 1, completion: 0, status: 'complete' }, profile).text, '≈Rp0,01');
});

test('invalid or incomplete counters and invalid tariffs never become monetary totals', () => {
    for (const value of [-1, NaN, Infinity, '100', null, undefined, 0.5, Number.MAX_SAFE_INTEGER + 1]) {
        assert.equal(estimateTokenCost({ ...turn, prompt: value }, profile), null);
        assert.equal(estimateTokenCost({ ...turn, completion: value }, profile), null);
    }
    for (const invalid of [null, {}, { ...profile, usd_to_idr: 0 }, { ...profile, usd_to_idr: Infinity },
        { ...profile, input_usd_per_million: -1 }, { ...profile, output_usd_per_million: '1.20' }]) {
        assert.equal(estimateTokenCost(turn, invalid), null);
    }
    assert.equal(estimateTokenCost(null, profile), null);
    assert.equal(estimateTokenCost(turn, profile, 'unexpected'), null);
});

function harness(costProfile = JSON.stringify(profile)) {
    const controls = new Map(['in', 'out', 'detail-in', 'detail-out', 'detail-total', 'session-total', 'measurement', 'cost-latest', 'cost-input', 'cost-output', 'cost-session']
        .map(key => [`[data-ai-token-${key}]`, { textContent: '', attributes: {}, setAttribute(key, value) { this.attributes[key] = value; } }]));
    const counter = {
        dataset: { costProfile, latestPrompt: 12918, latestCompletion: 173, latestStatus: 'complete',
            sessionPrompt: 25836, sessionCompletion: 346, sessionStatus: 'complete' },
        querySelector: selector => controls.get(selector), addEventListener() {},
    };
    const source = readFileSync(new URL('../../resources/js/ai/workspace.js', import.meta.url), 'utf8');
    const context = vm.createContext({ tokenDisplay, initTokenCost, document: { addEventListener() {} } });
    vm.runInContext(source.slice(source.indexOf('function initTokenCounter(')), context);
    const api = context.initTokenCounter({ querySelector: () => counter }, null);
    return { api, controls, text: key => controls.get(`[data-ai-token-${key}]`).textContent };
}

test('actual token counter initializes costs from persisted totals and replaces repeated status observations', () => {
    const ui = harness();
    assert.equal(ui.text('cost-latest'), '≈Rp73,24');
    assert.equal(ui.text('cost-input'), '≈Rp69,51');
    assert.equal(ui.text('cost-output'), '≈Rp3,72');
    assert.equal(ui.text('cost-session'), '≈Rp146,47');
    const session = { prompt: 1_000_000, completion: 0, status: 'complete' };
    ui.api.update(turn, session);
    ui.api.update(turn, session);
    assert.equal(ui.text('cost-session'), '≈Rp5.381,10');
    assert.equal(ui.text('detail-total'), '13,091');
    ui.api.update({ ...turn, status: 'partial' });
    assert.equal(ui.text('cost-latest'), '≈Rp73,24*');
    assert.equal(ui.text('cost-input'), '≈Rp69,51*');
    assert.equal(ui.text('cost-output'), '≈Rp3,72*');
    assert.equal(ui.text('cost-session'), '≈Rp5.381,10');
    ui.api.update(null, { prompt: 0, completion: 0, status: 'unknown' });
    assert.equal(ui.text('cost-session'), '—');
    assert.equal(ui.text('cost-latest'), '≈Rp73,24*');
    assert.equal(ui.text('cost-input'), '≈Rp69,51*');
    assert.equal(ui.text('cost-output'), '≈Rp3,72*');
});

test('disabled or malformed optional cost metadata cannot interrupt token updates', () => {
    for (const data of ['', 'not-json', 'null']) {
        const ui = harness(data);
        ui.api.update(turn, turn);
        assert.equal(ui.text('detail-total'), '13,091');
        assert.equal(ui.text('session-total'), '13,091 tok');
    }
    assert.doesNotThrow(() => initTokenCost({ dataset: { costProfile: '{}' }, querySelector: () => null }).update(turn, turn));
});
