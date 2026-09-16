/**
 * Run interactive work only after a prerendered document becomes visible.
 * Normal navigations still initialize as soon as the DOM is ready.
 */
export function runWhenPageIsActive(callback) {
    let initialized = false;

    const runOnce = () => {
        if (initialized) return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runOnce, { once: true });
            return;
        }

        initialized = true;

        try {
            callback();
        } catch (error) {
            console.error('Page initialization failed safely.', error);
        }
    };

    if (document.prerendering) {
        document.addEventListener('prerenderingchange', runOnce, { once: true });
        return;
    }

    runOnce();
}
