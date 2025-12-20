const THEME_STORAGE_KEY = 'theme';

const isBrowser = typeof window !== 'undefined' && typeof document !== 'undefined';

if (isBrowser) {
    const root = document.documentElement;
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const safeStorage = {
        get() {
            try {
                return window.localStorage.getItem(THEME_STORAGE_KEY);
            } catch (error) {
                console.warn('Unable to read theme from localStorage', error);
                return null;
            }
        },
        set(theme) {
            try {
                window.localStorage.setItem(THEME_STORAGE_KEY, theme);
            } catch (error) {
                console.warn('Unable to persist theme preference', error);
            }
        },
    };

    const normalizeTheme = (value) => (value === 'dark' ? 'dark' : 'light');
    const getStoredTheme = () => {
        const stored = safeStorage.get();
        return stored === 'dark' || stored === 'light' ? stored : null;
    };
    const hasStoredTheme = () => getStoredTheme() !== null;

    const updateToggleUi = (isDark) => {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) {
            return;
        }

        toggle.setAttribute('aria-pressed', String(isDark));

        const sun = toggle.querySelector('[data-icon="sun"]');
        const moon = toggle.querySelector('[data-icon="moon"]');
        const label = toggle.querySelector('[data-label="text"]');

        if (sun) {
            sun.classList.toggle('hidden', isDark);
        }
        if (moon) {
            moon.classList.toggle('hidden', !isDark);
        }
        if (label) {
            label.textContent = isDark ? 'Mode terang' : 'Mode gelap';
        }
    };

    const applyTheme = (theme, persist = false) => {
        const normalized = normalizeTheme(theme);
        const isDark = normalized === 'dark';

        root.classList.toggle('dark', isDark);
        root.dataset.theme = normalized;
        updateToggleUi(isDark);

        if (persist) {
            safeStorage.set(normalized);
        }
    };

    const resolveInitialTheme = () => {
        const storedTheme = getStoredTheme();
        if (storedTheme) {
            return storedTheme;
        }

        const datasetTheme = root.dataset?.theme;
        if (datasetTheme === 'dark' || datasetTheme === 'light') {
            return datasetTheme;
        }

        return mediaQuery.matches ? 'dark' : 'light';
    };

    const bindToggle = () => {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) {
            return;
        }

        if (toggle.dataset.themeBound === 'true') {
            updateToggleUi(root.classList.contains('dark'));
            return;
        }

        toggle.dataset.themeBound = 'true';
        toggle.addEventListener('click', () => {
            const nextTheme = root.classList.contains('dark') ? 'light' : 'dark';
            applyTheme(nextTheme, true);
        });

        updateToggleUi(root.classList.contains('dark'));
    };

    const initialize = () => {
        applyTheme(resolveInitialTheme());
        bindToggle();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }

    const handleMediaChange = (event) => {
        if (!hasStoredTheme()) {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    };

    if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', handleMediaChange);
    } else if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(handleMediaChange);
    }

    document.addEventListener('livewire:navigated', () => {
        bindToggle();
    });
}
