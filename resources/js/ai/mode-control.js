import { animateEffortSlider } from './effort-slider.js';

const MODES = ['default', 'eco'];
const LABELS = { default: 'Default', eco: 'Eco' };
const INITIAL_MODE = 'eco';

const normalizeMode = value => MODES.includes(value) ? value : INITIAL_MODE;

// Each submission captures this preference before retries can change the UI.
export function initModeControl(root, storage) {
    const toggle = root.querySelector('[data-ai-eco-toggle]');
    const slider = root.querySelector('[data-ai-mode-slider]');
    if (!slider) return;

    const label = root.querySelector('[data-ai-mode-label]');
    const control = root.querySelector('[data-ai-settings-mode]');
    const dialog = root.querySelector('[data-ai-settings]');
    const form = slider.closest('form');
    const scope = root.dataset.modePreferenceScope;
    const key = scope ? `polylife.ai.mode.${scope}` : null;
    let current = INITIAL_MODE;
    let paintedIndex = null;

    try {
        storage ??= globalThis.localStorage;
        if (key) current = normalizeMode(storage?.getItem(key));
    } catch {
        // Restricted browser storage must never prevent chat from loading.
    }

    const preview = mode => {
        const index = MODES.indexOf(mode);
        slider.value = String(index);
        animateEffortSlider(slider, paintedIndex, index, MODES.length);
        paintedIndex = index;
        slider.setAttribute('aria-valuetext', LABELS[mode]);
        label.textContent = LABELS[mode];
        control.dataset.mode = mode;
    };

    const paint = () => {
        root.dataset.aiResponseMode = current;
        if (toggle) {
            toggle.setAttribute('aria-pressed', String(current === 'eco'));
            toggle.title = current === 'eco' ? 'Mode Eco · Gunakan Default' : 'Mode Default · Aktifkan Eco';
        }
        preview(current);
    };

    const commit = mode => {
        current = normalizeMode(mode);
        try {
            if (key) storage?.setItem(key, current);
        } catch {
            // Keep the selection usable for this page when persistence is unavailable.
        }
        paint();
    };

    toggle?.addEventListener('click', () => commit(current === 'eco' ? 'default' : 'eco'));
    slider.addEventListener('input', () => preview(normalizeMode(MODES[Number(slider.value)])));
    form.addEventListener('submit', () => commit(MODES[Number(slider.value)]));
    dialog.addEventListener('close', () => preview(current));
    paint();
}
