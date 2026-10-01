import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

function readRules(path) {
    const css = readFileSync(new URL(path, import.meta.url), 'utf8')
        .replace(/\/\*[\s\S]*?\*\//g, '');
    return [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)];
}

const rules = readRules('../../resources/css/ai/theme.css');
const switchRules = readRules('../../resources/css/workspace-mode-switch.css');

function palette(dark, targets = ['.ai-workspace'], source = rules, prefix = 'ai-') {
    const declarations = darkRule => {
        const matching = source.filter(([, selectors]) => selectors.split(',').some(selector =>
            targets.some(target => selector.trim() === `${darkRule ? '.dark ' : ''}${target}`)));
        assert.ok(matching.length, `The ${darkRule ? 'dark' : 'light'} palette for ${targets.join(', ')} must be declared.`);
        return Object.assign({}, ...matching.map(([, , body]) =>
            Object.fromEntries([...body.matchAll(/--([\w-]+)\s*:\s*([^;]+);/g)]
                .map(([, name, value]) => [name, value.trim()]))));
    };
    const colors = { ...declarations(false), ...(dark ? declarations(true) : {}) };
    const resolve = (name, trail = new Set()) => {
        assert.ok(!trail.has(name), `Circular theme alias: ${name}`);
        const alias = colors[name]?.match(/^var\(--([\w-]+)\)$/);
        return alias ? resolve(alias[1], new Set([...trail, name])) : colors[name];
    };
    return Object.fromEntries(Object.keys(colors).filter(name => name.startsWith(prefix))
        .map(name => [name.slice(prefix.length), resolve(name)]));
}

function luminance(hex) {
    assert.match(hex ?? '', /^#[\da-f]{6}$/i, 'Contrast pairs must resolve to opaque sRGB colors.');
    const channels = hex.slice(1).match(/../g).map(channel => {
        const value = parseInt(channel, 16) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function assertContrast(colors, foreground, background, minimum) {
    const a = luminance(colors[foreground]);
    const b = luminance(colors[background]);
    const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    assert.ok(ratio >= minimum,
        `${foreground} (${colors[foreground]}) on ${background} (${colors[background]}) is ${ratio.toFixed(2)}:1; requires ${minimum}:1.`);
}

function gridCanvas(colors, layers) {
    const grid = colors['grid-color'].match(/^rgb\((\d+) (\d+) (\d+) \/ ([\d.]+)\)$/);
    assert.ok(grid, 'The grid must declare its sRGB channels and opacity.');
    const alpha = 1 - (1 - Number(grid[4])) ** layers;
    const canvas = colors.canvas.slice(1).match(/../g).map(channel => parseInt(channel, 16));
    return '#' + canvas.map((channel, index) => Math.round(channel * (1 - alpha) + Number(grid[index + 1]) * alpha)
        .toString(16).padStart(2, '0')).join('');
}

for (const dark of [false, true]) {
    const mode = dark ? 'dark' : 'light';
    const colors = palette(dark);
    const sidebar = palette(dark, ['#app-sidebar', '#app-sidebar.sidebar-mode-ai']);
    const menu = palette(dark, ['.ai-history-menu']);
    const modeSwitch = palette(dark, ['.workspace-mode-switch'], switchRules, 'mode-');

    test(`${mode}: text and controls remain readable over grid lines and intersections`, () => {
        for (const layers of [1, 2]) {
            const rendered = { ...colors, 'grid-canvas': gridCanvas(colors, layers) };
            for (const text of ['text', 'muted', 'accent', 'danger', 'success']) {
                assertContrast(rendered, text, 'grid-canvas', 4.5);
            }
            for (const foreground of ['brand', 'control-line']) {
                assertContrast(rendered, foreground, 'grid-canvas', 3);
            }
        }
    });

    test(`${mode}: lilac branding stays visible in large greetings and assistant icons`, () => {
        assertContrast(colors, 'brand', 'canvas', 3);
        assertContrast(colors, 'brand', 'surface', 3);
        assertContrast(colors, 'brand', 'decoration', 3);
    });

    test(`${mode}: normal text stays readable across chat, controls, and code`, () => {
        for (const surface of ['canvas', 'surface', 'control', 'soft', 'selected', 'code-surface', 'code-header']) {
            assertContrast(colors, 'text', surface, 4.5);
        }
    });

    test(`${mode}: secondary text and placeholders retain normal-text contrast`, () => {
        for (const surface of ['canvas', 'surface', 'control', 'soft', 'selected', 'code-header']) {
            assertContrast(colors, 'muted', surface, 4.5);
        }
    });

    test(`${mode}: accent text remains readable in links, active navigation, and token details`, () => {
        for (const surface of ['canvas', 'surface', 'control', 'soft', 'selected']) {
            assertContrast(colors, 'accent', surface, 4.5);
        }
    });

    test(`${mode}: error and confirmed-action status text stays readable`, () => {
        for (const status of ['danger', 'success']) {
            for (const surface of ['canvas', 'surface', 'control', 'soft']) {
                assertContrast(colors, status, surface, 4.5);
            }
        }
    });

    test(`${mode}: send icons, primary labels, and selection checks retain contrast`, () => {
        for (const state of ['primary', 'primary-hover']) {
            assertContrast(colors, 'on-primary', state, 4.5);
        }
        // Selection indicators keep their primary fill when the card is hovered.
        assertContrast(colors, 'selection-mark', 'primary', 3);
    });

    test(`${mode}: disabled controls keep a visible label and icon without whole-button opacity`, () => {
        assertContrast(colors, 'disabled-ink', 'disabled-surface', 3);
    });

    test(`${mode}: keyboard-focus rings are visible against their adjacent surfaces`, () => {
        for (const surface of ['canvas', 'surface', 'control', 'soft', 'selected']) {
            assertContrast(colors, 'accent', surface, 3);
        }
    });

    test(`${mode}: essential input outlines remain distinguishable from input and outer surfaces`, () => {
        // Decorative dividers intentionally use a separate, softer `line` token.
        for (const surface of ['canvas', 'surface', 'control']) {
            assertContrast(colors, 'control-line', surface, 3);
        }
    });

    test(`${mode}: actual sidebar text stays readable in idle, hover, and selected states`, () => {
        for (const surface of ['sidebar', 'surface', 'control', 'soft', 'selected']) {
            for (const foreground of ['text', 'muted', 'accent', 'danger']) {
                assertContrast(sidebar, foreground, surface, 4.5);
            }
        }
    });

    test(`${mode}: sidebar search outlines, active markers, and keyboard focus remain visible`, () => {
        for (const surface of ['sidebar', 'surface', 'control', 'soft', 'selected']) {
            assertContrast(sidebar, 'accent', surface, 3);
        }
        for (const surface of ['sidebar', 'surface', 'control']) {
            assertContrast(sidebar, 'control-line', surface, 3);
        }
    });

    test(`${mode}: shared brand subtitle stays readable in both AI and workspace sidebars`, () => {
        const shared = palette(dark, ['#app-sidebar']);
        assert.equal(shared['brand-subtitle'], sidebar['brand-subtitle']);
        assertContrast(sidebar, 'brand-subtitle', 'sidebar', 4.5);
        // Shared workspace uses white / slate-950 surfaces rather than the AI palette.
        assertContrast({ ...shared, 'workspace-sidebar': dark ? '#020617' : '#ffffff' },
            'brand-subtitle', 'workspace-sidebar', 4.5);
    });

    test(`${mode}: body-portaled history menus retain readable labels and visible focus`, () => {
        for (const surface of ['surface', 'soft']) {
            for (const foreground of ['text', 'muted', 'danger']) {
                assertContrast(menu, foreground, surface, 4.5);
            }
            assertContrast(menu, 'accent', surface, 3);
        }
    });

    test(`${mode}: independently themed mode switch retains readable states in either sidebar`, () => {
        assertContrast(modeSwitch, 'muted', 'control', 4.5);
        assertContrast(modeSwitch, 'text', 'soft', 4.5);
        assertContrast(modeSwitch, 'accent', 'selected', 4.5);
        const adjacent = { ...modeSwitch, 'ai-sidebar': sidebar.sidebar,
            'workspace-sidebar': dark ? '#020617' : '#ffffff' };
        for (const surface of ['control', 'soft', 'selected', 'ai-sidebar', 'workspace-sidebar']) {
            assertContrast(adjacent, 'accent', surface, 3);
        }
    });
}
