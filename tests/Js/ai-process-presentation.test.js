import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/ai/workspace.js', import.meta.url), 'utf8');
const functionSource = source.slice(source.indexOf('function appendProcess('), source.indexOf('function initMessageInteractions('));

class Element {
    constructor(tag) { this.tag = tag; this.children = []; this.dataset = {}; this.classList = { add() {} }; this.textContent = ''; }
    append(...nodes) { this.children.push(...nodes); }
    prepend(node) { this.children.unshift(node); }
    setAttribute(key, value) { this[key] = value; }
    set innerHTML(value) {
        this.markup = value;
        this.heading = new Element('span');
        this.list = new Element('ol');
        this.children.push(this.heading, this.list);
    }
    querySelector(selector) { return selector === 'summary span' ? this.heading : selector === 'ol' ? this.list : null; }
}

function render(steps) {
    const body = new Element('div');
    const document = { createElement: tag => new Element(tag), querySelector: () => null };
    const message = { closest: () => null, querySelector: selector => selector === '.ai-message-body' ? body : null };
    const context = vm.createContext({ document, message, run: { duration_ms: 253263, steps } });
    vm.runInContext(functionSource + '\nappendProcess(message, run);', context);
    return body.children[0];
}

const script = (status, failure = null) => ({ kind: 'tool_call', status, execution_mode: 'client_script', duration_ms: 54965,
    label: status === 'failed' ? 'Skrip 1 gagal' : 'Skrip 1 selesai',
    client_computation: { description: '<img src=x onerror=alert(1)> model', source: 'function compute(){}', inputs: {}, result: null, failure_message: failure } });

test('completed chat process identifies failed script attempts separately from success', () => {
    const process = render([script('failed', 'Skrip gagal: sintaks JavaScript tidak valid.'), script('completed')]);
    assert.match(process.heading.textContent, /2 skrip.*1 gagal/);
    const failed = process.list.children[0];
    assert.equal(failed.dataset.status, 'failed');
    const copy = failed.children[1];
    assert.match(copy.children.at(-1).textContent, /Gagal$/);
    const detail = copy.children.find(child => child.tag === 'details');
    assert.equal(detail.children.find(child => child.className === 'ai-client-computation-error').role, 'status');
});

test('model narration remains text outside the literal source block', () => {
    const process = render([script('completed')]);
    const detail = process.list.children[0].children[1].children.find(child => child.tag === 'details');
    const narration = detail.children.find(child => child.className === 'ai-client-computation-description');
    const code = detail.children.find(child => child.tag === 'pre');
    assert.match(narration.textContent, /<img src=x/);
    assert.equal(narration.markup, undefined);
    assert.ok(detail.children.indexOf(narration) < detail.children.indexOf(code));
    assert.ok(code.textContent.startsWith('function compute(){}'));
    assert.ok(!code.textContent.includes('Maksud perhitungan'));
});
