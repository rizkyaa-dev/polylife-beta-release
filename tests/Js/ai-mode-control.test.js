import test from 'node:test';
import assert from 'node:assert/strict';
import { initModeControl } from '../../resources/js/ai/mode-control.js';

function element() {
    const handlers = new Map();
    return {
        dataset: {}, attributes: {},
        addEventListener(type, handler) { handlers.set(type, handler); },
        setAttribute(name, value) { this.attributes[name] = value; },
        emit(type) { handlers.get(type)?.(); },
    };
}

function harness(storage = new Map(), scope = '7', hasToggle = true) {
    const toggle = element();
    const slider = element();
    const sliderClasses = new Set();
    slider.clientWidth = 240;
    slider.style = { values: {}, setProperty(name, value) { this.values[name] = value; } };
    slider.classList = {
        add: name => sliderClasses.add(name),
        remove: name => sliderClasses.delete(name),
        contains: name => sliderClasses.has(name),
    };
    const label = element();
    const control = element();
    const dialog = element();
    const form = element();
    slider.closest = () => form;
    const root = {
        dataset: { modePreferenceScope: scope },
        querySelector: selector => ({
            '[data-ai-eco-toggle]': hasToggle ? toggle : null,
            '[data-ai-mode-slider]': slider,
            '[data-ai-mode-label]': label,
            '[data-ai-settings-mode]': control,
            '[data-ai-settings]': dialog,
        })[selector] ?? null,
    };
    const adapter = storage instanceof Map ? {
        getItem: key => storage.get(key),
        setItem: (key, value) => storage.set(key, value),
    } : storage;
    initModeControl(root, adapter);
    return { root, toggle, slider, label, control, dialog, form, storage };
}

test('leaf toggles the selection and restores it after reinitialization', () => {
    const ui = harness();
    assert.equal(ui.root.dataset.aiResponseMode, 'eco');
    assert.equal(ui.toggle.attributes['aria-pressed'], 'true');
    assert.equal(ui.slider.value, '1');
    assert.equal(ui.label.textContent, 'Eco');
    assert.equal(ui.storage.size, 0);
    ui.toggle.emit('click');
    assert.equal(ui.toggle.attributes['aria-pressed'], 'false');
    assert.equal(ui.slider.value, '0');
    assert.equal(ui.label.textContent, 'Default');
    const restored = harness(ui.storage);
    assert.equal(restored.root.dataset.aiResponseMode, 'default');
    restored.toggle.emit('click');
    assert.equal(restored.slider.attributes['aria-valuetext'], 'Eco');
    assert.equal(ui.storage.get('polylife.ai.mode.7'), 'eco');
});

test('settings preview is discarded when the dialog is cancelled', () => {
    const ui = harness();
    assert.equal(ui.slider.classList.contains('ai-effort-sliding'), false);
    ui.slider.value = '0';
    ui.slider.emit('input');
    assert.equal(ui.label.textContent, 'Default');
    assert.equal(ui.slider.classList.contains('ai-effort-sliding'), true);
    assert.equal(ui.slider.style.values['--ai-effort-slide-offset'], '212px');
    assert.equal(ui.toggle.attributes['aria-pressed'], 'true');
    assert.equal(ui.storage.size, 0);
    ui.dialog.emit('close');
    assert.equal(ui.slider.value, '1');
    assert.equal(ui.label.textContent, 'Eco');
    assert.equal(ui.slider.style.values['--ai-effort-slide-offset'], '-212px');
});

test('saving settings synchronizes the leaf and persists the selected mode', () => {
    const ui = harness();
    ui.slider.value = '0';
    ui.slider.emit('input');
    ui.form.emit('submit');
    ui.dialog.emit('close');
    assert.equal(ui.toggle.attributes['aria-pressed'], 'false');
    assert.equal(ui.storage.get('polylife.ai.mode.7'), 'default');
    assert.equal(ui.slider.attributes['aria-valuetext'], 'Default');
});

test('preferences stay separate for different accounts in the same browser', () => {
    const storage = new Map();
    harness(storage, '7').toggle.emit('click');
    assert.equal(harness(storage, '8').label.textContent, 'Eco');
    assert.equal(harness(storage, '7').label.textContent, 'Default');
});

test('unrecognized stored values safely fall back to Eco', () => {
    const ui = harness(new Map([['polylife.ai.mode.7', 'invalid']]));
    assert.equal(ui.label.textContent, 'Eco');
    assert.equal(ui.slider.attributes['aria-valuetext'], 'Eco');
});

test('blocked browser storage does not prevent controls from working', () => {
    const ui = harness({
        getItem() { throw new Error('Storage unavailable'); },
        setItem() { throw new Error('Storage unavailable'); },
    });
    assert.equal(ui.root.dataset.aiResponseMode, 'eco');
    assert.doesNotThrow(() => ui.toggle.emit('click'));
    assert.equal(ui.label.textContent, 'Default');
    ui.slider.value = '1';
    assert.doesNotThrow(() => ui.form.emit('submit'));
    assert.equal(ui.toggle.attributes['aria-pressed'], 'true');
});

test('the optional module is safe when its controls are absent', () => {
    assert.doesNotThrow(() => initModeControl({ querySelector: () => null }));
});

test('settings remain usable when the provider has no reasoning popover', () => {
    const ui = harness(new Map(), '7', false);
    ui.slider.value = '0';
    ui.slider.emit('input');
    assert.equal(ui.label.textContent, 'Default');
    assert.doesNotThrow(() => ui.form.emit('submit'));
    assert.equal(ui.storage.get('polylife.ai.mode.7'), 'default');
});
