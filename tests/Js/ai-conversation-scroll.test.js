import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createConversationScroller } from '../../resources/js/ai/conversation-scroller.js';
import { PendingAiTurns } from '../../resources/js/ai/pending-turns.js';
import { PendingAiTurn, isDefinitiveFailure } from '../../resources/js/ai/turn-recovery.js';

function layoutHarness(t) {
    const frames = new Map();
    const observers = [];
    const pageListeners = new Map();
    const saved = new Map();
    let sequence = 0;
    for (const [key, value] of Object.entries({
        requestAnimationFrame: callback => { frames.set(++sequence, callback); return sequence; },
        cancelAnimationFrame: id => frames.delete(id),
        addEventListener: (name, handler) => pageListeners.set(name, handler),
        removeEventListener: name => pageListeners.delete(name),
        ResizeObserver: class {
            targets = [];
            constructor(callback) { this.callback = callback; observers.push(this); }
            observe(target) { this.targets.push(target); }
            disconnect() { this.targets = []; }
        },
    })) {
        saved.set(key, Object.getOwnPropertyDescriptor(globalThis, key));
        Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    }
    t.after(() => {
        for (const [key, descriptor] of saved) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    });
    const listeners = new Map();
    let top = 0;
    const viewport = {
        clientHeight: 200, scrollHeight: 1000,
        get scrollTop() { return top; },
        set scrollTop(value) { top = Math.max(0, Math.min(value, this.scrollHeight - this.clientHeight)); },
        addEventListener: (name, handler) => listeners.set(name, handler),
        removeEventListener: name => listeners.delete(name),
    };
    return {
        viewport, observers, frames,
        userScroll(value) { viewport.scrollTop = value; listeners.get('scroll')?.(); },
        resize() { observers.forEach(observer => observer.callback()); },
        pageHide(persisted) { pageListeners.get('pagehide')?.({ persisted }); },
        flush() { const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback()); },
    };
}

test('sending follows the final layout after both the new message and thinking indicator are inserted', t => {
    const ui = layoutHarness(t);
    const content = {};
    const scroll = createConversationScroller(ui.viewport, content);
    scroll.scrollToLatest();
    ui.viewport.scrollHeight += 60;
    scroll.refresh();
    ui.viewport.scrollHeight += 84;
    ui.viewport.clientHeight = 160;
    ui.resize();
    assert.equal(ui.frames.size, 1);
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 984);
    assert.deepEqual(ui.observers[0].targets, [ui.viewport, content]);
    scroll.dispose();
});

test('delayed math or media expansion and a smaller viewport keep the latest content visible', t => {
    const ui = layoutHarness(t);
    const scroll = createConversationScroller(ui.viewport, {});
    scroll.scrollToLatest();
    ui.flush();
    ui.viewport.scrollHeight += 500;
    ui.viewport.clientHeight -= 80;
    ui.resize();
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 1380);
    scroll.dispose();
});

test('reading older messages cancels a queued auto-scroll and suppresses later layout updates', t => {
    const ui = layoutHarness(t);
    const scroll = createConversationScroller(ui.viewport, {});
    scroll.scrollToLatest();
    ui.flush();
    ui.viewport.scrollHeight += 500;
    ui.resize();
    ui.userScroll(300);
    ui.flush();
    ui.resize();
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 300);
    scroll.dispose();
});

test('returning to the bottom or sending another message resumes following', t => {
    const ui = layoutHarness(t);
    const scroll = createConversationScroller(ui.viewport, {});
    scroll.scrollToLatest();
    ui.flush();
    ui.userScroll(300);
    ui.userScroll(800);
    ui.viewport.scrollHeight += 100;
    ui.resize();
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 900);
    ui.userScroll(300);
    scroll.scrollToLatest();
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 900);
    scroll.dispose();
});

test('prepending history preserves the current reading position instead of following the new bottom', t => {
    const ui = layoutHarness(t);
    const scroll = createConversationScroller(ui.viewport, {});
    scroll.scrollToLatest();
    ui.flush();
    scroll.preservePosition(() => { ui.viewport.scrollHeight += 600; });
    ui.resize();
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 1400);
    ui.viewport.scrollHeight += 100;
    ui.resize();
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 1400);
    scroll.dispose();
});

test('disposing releases observers and any pending animation frame', t => {
    const ui = layoutHarness(t);
    const scroll = createConversationScroller(ui.viewport, {});
    scroll.scrollToLatest();
    scroll.dispose();
    scroll.dispose();
    assert.equal(ui.frames.size, 0);
    assert.deepEqual(ui.observers[0].targets, []);
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 0);
});

test('explicit scrolling still works when ResizeObserver is unavailable', t => {
    const ui = layoutHarness(t);
    globalThis.ResizeObserver = undefined;
    const scroll = createConversationScroller(ui.viewport, {});
    scroll.scrollToLatest();
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 800);
    scroll.dispose();
});

test('browser history caching retains following while leaving the page releases pending work', t => {
    const ui = layoutHarness(t);
    createConversationScroller(ui.viewport, {});
    ui.resize();
    ui.pageHide(true);
    ui.flush();
    ui.viewport.scrollHeight += 100;
    ui.resize();
    assert.equal(ui.frames.size, 1);
    ui.pageHide(false);
    assert.equal(ui.frames.size, 0);
    assert.deepEqual(ui.observers[0].targets, []);
});

function workspaceHarness(t) {
    const ui = layoutHarness(t);
    const handlers = new Map();
    const items = [];
    let resolveCompletion;
    const createMessage = (role, height) => {
        const text = { textContent: '' };
        const node = {
            role, height, dataset: {}, isConnected: false,
            querySelector: selector => selector === '[data-message-text]' ? text : null,
            remove() { const index = items.indexOf(node); if (index >= 0) { items.splice(index, 1); ui.viewport.scrollHeight -= height; } node.isConnected = false; },
        };
        return node;
    };
    const list = {
        querySelector: selector => selector === '[data-ai-thinking-indicator]' ? items.find(node => node.role === 'thinking') : null,
        append(node) { items.push(node); node.isConnected = true; ui.viewport.scrollHeight += node.height; },
    };
    const input = { value: 'Pesan baru', style: {}, scrollHeight: 32, focus() {}, addEventListener() {} };
    const form = { addEventListener: (type, handler) => handlers.set(type, handler), setAttribute() {} };
    const error = { hidden: true, querySelector: () => ({ textContent: '' }) };
    const dialog = { querySelectorAll: () => [], addEventListener() {}, hasAttribute: () => false };
    const send = { querySelector: () => ({}), addEventListener() {}, setAttribute() {} };
    const root = {
        dataset: { chatUrl: '/ai/chat', workspaceUrl: 'http://localhost/ai' }, classList: { add() {} },
        querySelector: selector => ({
            '[data-ai-input]': input, '[data-ai-form]': form, '[data-ai-send]': send,
            '[data-ai-messages]': ui.viewport, '[data-ai-message-list]': list,
            '[data-ai-error]': error, '[data-ai-settings]': dialog,
            '[data-ai-welcome]': {}, '[data-ai-suggestions]': {},
            ...Object.fromEntries(['user', 'assistant', 'thinking'].map(role => [
                `[data-ai-${role}-template]`, { content: { firstElementChild: { cloneNode: () => createMessage(role, role === 'thinking' ? 84 : 60) } } },
            ])),
        })[selector] ?? null,
        querySelectorAll: () => [],
    };
    const source = readFileSync(new URL('../../resources/js/ai/workspace.js', import.meta.url), 'utf8');
    const workspace = source.slice(source.indexOf('function initWorkspace('), source.indexOf('\nfunction appendProcess('));
    const context = vm.createContext({
        root, createConversationScroller, PendingAiTurn, PendingAiTurns, isDefinitiveFailure, URL,
        document: { querySelectorAll: () => [] },
        window: { matchMedia: () => ({ matches: false }), history: { replaceState() {} } },
        initHistory: () => ({ update() {} }), initThinkingControl() {}, initSettingsEffortSlider() {},
        initTokenCounter: () => ({ update() {}, updateDraft() {} }), renderMessageMath: async () => {},
        initMessageInteractions() {}, initEarlierMessages() {}, initProposals() {}, initCodeArtifacts() {}, appendProcess() {},
        createRequestId: () => 'scroll-test', setMessageIdentity() {},
        postJson: async () => ({ run_id: 7, session_id: 3 }),
        waitForRun: () => new Promise(resolve => { resolveCompletion = resolve; }),
    });
    vm.runInContext(workspace + '\ninitWorkspace(root);', context);
    ui.flush();
    return {
        ...ui, items,
        submit: () => handlers.get('submit')({ preventDefault() {} }),
        complete: data => resolveCompletion(data),
    };
}

test('actual composer reveals the thinking indicator before the server finishes', async t => {
    const ui = workspaceHarness(t);
    const sending = ui.submit();
    ui.flush();
    assert.equal(ui.items.at(-1).role, 'thinking');
    assert.equal(ui.viewport.scrollTop, ui.viewport.scrollHeight - ui.viewport.clientHeight);
    await new Promise(resolve => setImmediate(resolve));
    ui.complete({ reply: 'Jawaban', session_id: 3, proposals: [] });
    await sending;
    ui.flush();
});

test('actual composer does not pull the reader down when the assistant reply arrives', async t => {
    const ui = workspaceHarness(t);
    const sending = ui.submit();
    ui.flush();
    ui.userScroll(300);
    await new Promise(resolve => setImmediate(resolve));
    ui.complete({ reply: 'Jawaban', session_id: 3, proposals: [] });
    await sending;
    ui.flush();
    assert.equal(ui.viewport.scrollTop, 300);
});
