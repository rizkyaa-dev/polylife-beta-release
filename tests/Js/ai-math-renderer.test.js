import test from 'node:test';
import assert from 'node:assert/strict';
import { renderExpression } from '../../resources/js/ai/math-expression.js';
import { renderMessageMath } from '../../resources/js/ai/math-renderer.js';

function expression(source, mode = 'inline') {
    return { textContent: source, innerHTML: '', dataset: { aiMath: mode }, classList: { add() {} } };
}

test('reactor equations render fractions, indices and accessible MathML', () => {
    const element = expression(String.raw`\varepsilon_p\frac{\partial C_{i,p}}{\partial t}=\frac{1}{r^2}\frac{\partial}{\partial r}\left(r^2D_{i,\mathrm{eff}}\frac{\partial C_{i,p}}{\partial r}\right)`, 'display');
    renderExpression(element);
    assert.equal(element.dataset.mathRendered, 'true');
    assert.match(element.innerHTML, /katex-display/);
    assert.match(element.innerHTML, /<math/);
    assert.match(element.innerHTML, /mfrac/);
});

test('invalid syntax falls back to original text without breaking the message', () => {
    const source = String.raw`\frac{broken`;
    const element = expression(source);
    renderExpression(element);
    assert.equal(element.dataset.mathRendered, 'error');
    assert.equal(element.textContent, source);
    assert.equal(element.innerHTML, '');
});

test('untrusted math cannot create links, images or HTML event handlers', () => {
    for (const source of [String.raw`\href{javascript:alert(1)}{click}`, String.raw`\includegraphics{https://example.com/pixel}`, String.raw`\htmlData{onclick=alert(1)}{x}`, String.raw`\text{<img src=x onerror=alert(1)>}`]) {
        const element = expression(source);
        renderExpression(element);
        assert.doesNotMatch(element.innerHTML, /<a\b|<img\b|\sonclick=/);
    }
});

test('recursive macros are bounded and definitions cannot leak to another formula', () => {
    const loop = expression(String.raw`\def\a{\a}\a`);
    renderExpression(loop);
    assert.equal(loop.dataset.mathRendered, 'error');
    const first = expression(String.raw`\gdef\custom{1}\custom`);
    renderExpression(first);
    const second = expression(String.raw`\custom`);
    renderExpression(second);
    assert.equal(second.dataset.mathRendered, 'error');
});

test('plain messages do not load the optional math engine', async () => {
    await renderMessageMath({ querySelectorAll: () => [] });
});
