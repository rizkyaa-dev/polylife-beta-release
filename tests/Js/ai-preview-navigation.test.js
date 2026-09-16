import test from 'node:test';
import assert from 'node:assert/strict';
import { previewFragmentHref } from '../../resources/js/ai/code-artifacts.js';

test('preview fragments never resolve against the application URL', () => {
    for (const fragment of ['#menu', '#kontak', '#', '#bagian%20dua', '#kopi-☕']) {
        assert.equal(previewFragmentHref(fragment), `about:srcdoc${fragment}`);
    }
    assert.equal(previewFragmentHref('  #menu  '), 'about:srcdoc#menu');
});

test('non-fragment URLs and already explicit preview links are not rewritten', () => {
    for (const href of ['about:srcdoc#menu', 'https://example.com/', 'mailto:hello@example.com', '/menu', '']) {
        assert.equal(previewFragmentHref(href), href);
    }
});
