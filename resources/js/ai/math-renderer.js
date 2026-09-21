let renderer;

export async function renderMessageMath(container) {
    const expressions = [...container.querySelectorAll('[data-ai-math]:not([data-math-rendered])')];
    if (!expressions.length) return;

    try {
        // Load local code and fonts only for messages containing mathematics.
        renderer ??= import('./math-engine.js');
        const { renderExpression } = await renderer;
        for (const element of expressions) {
            if (element.dataset.mathRendered) continue;
            renderExpression(element);
        }
    } catch {
        renderer = undefined; // Allow a later message to retry a failed asset load.
        // Escaped source remains readable; optional rendering must not break chat.
    }
}
