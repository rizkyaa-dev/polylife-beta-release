import test from 'node:test';
import assert from 'node:assert/strict';
import { tokenDisplay } from '../../resources/js/ai/token-usage.js';

test('missing measurements are never presented as a measured zero', () => {
    assert.equal(tokenDisplay().total, '—');
    assert.equal(tokenDisplay({total: 0, status: 'unknown'}).total, '—');
    assert.equal(tokenDisplay({total: 0, status: 'complete'}).total, '0');
});

test('partial measurements display a lower bound', () => {
    assert.equal(tokenDisplay({prompt: 12, completion: 8, total: 20, status: 'partial'}).total, '≥20');
    assert.equal(tokenDisplay({status: 'partial'}).label, 'Sebagian tercatat');
});
