import katex from 'katex';

export function renderExpression(element) {
    const source = element.textContent;
    if (source.length > 8192) return;

    try {
        // Only KaTeX-generated, untrusted-mode HTML reaches this sink.
        element.innerHTML = katex.renderToString(source, {
            displayMode: element.dataset.aiMath === 'display',
            output: 'htmlAndMathml',
            throwOnError: true,
            trust: false,
            strict: 'error',
            maxExpand: 500,
            maxSize: 10,
            macros: {},
        });
        element.dataset.mathRendered = 'true';
    } catch {
        element.textContent = source;
        element.classList.add('ai-math-error');
        element.dataset.mathRendered = 'error';
        element.title = 'Rumus tidak dapat dirender; sumber asli ditampilkan.';
    }
}
