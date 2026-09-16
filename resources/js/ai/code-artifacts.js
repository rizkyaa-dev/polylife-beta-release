const PREVIEW_CSP = "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src https: data: blob:; font-src https: data:; media-src https: data: blob:; connect-src 'none'; frame-src 'none'; object-src 'none'; form-action 'none'; base-uri 'none'";
const PREVIEW_METADATA = `<meta http-equiv="Content-Security-Policy" content="${PREVIEW_CSP}"><meta name="referrer" content="no-referrer"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">`;
// srcdoc inherits the parent base URL. Keep fragment navigation in the preview,
// including links created dynamically; never grant access to the parent origin.
const PREVIEW_NAVIGATION = `<script>document.addEventListener('click', function(event) {
    const link = event.target instanceof Element ? event.target.closest('a[href],area[href]') : null;
    if (!link) return;
    const href = (link.getAttribute('href') ?? '').trim();
    if (href.startsWith('#')) link.setAttribute('href', 'about:srcdoc' + href);
}, true);<\/script>`;

export function initCodeArtifacts(root) {
    const preview = root.querySelector('[data-code-preview]');
    const frame = preview?.querySelector('[data-code-preview-frame]');
    const title = preview?.querySelector('[data-code-preview-title]');
    let previewOpener = null;

    if (!preview || !frame || !title) return;

    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-code-copy], [data-code-download], [data-code-run]');
        if (!button) return;

        const artifact = button.closest('[data-code-artifact]');
        const code = artifact?.querySelector('code')?.textContent ?? '';
        if (!artifact || !code) return;

        if (button.matches('[data-code-copy]')) {
            await copyCode(code, button, artifact);
            return;
        }

        if (button.matches('[data-code-download]')) {
            downloadCode(code, artifact.dataset.codeFilename, artifact.dataset.codeLanguage);
            announce(artifact, 'File kode mulai di-download.');
            return;
        }

        if (button.matches('[data-code-run]') && artifact.dataset.codeRunnable === 'true') {
            previewOpener = button;
            frame.srcdoc = securePreviewDocument(code);
            title.textContent = documentTitle(code) || artifact.dataset.codeFilename || 'Preview kode';
            preview.hidden = false;
            root.classList.add('has-code-preview');
            preview.querySelector('[data-code-preview-close]')?.focus({ preventScroll: true });
        }
    });

    const closePreview = () => {
        preview.hidden = true;
        frame.srcdoc = '';
        root.classList.remove('has-code-preview');
        previewOpener?.focus({ preventScroll: true });
        previewOpener = null;
    };

    preview.querySelector('[data-code-preview-close]')?.addEventListener('click', closePreview);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !preview.hidden) closePreview();
    });
}

async function copyCode(code, button, artifact) {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(code);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = code;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.append(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }

        temporaryLabel(button, 'Copied');
        announce(artifact, 'Kode berhasil disalin.');
    } catch {
        announce(artifact, 'Kode belum dapat disalin. Pilih kode lalu salin secara manual.');
    }
}

function downloadCode(code, rawFilename, language) {
    const filename = String(rawFilename || 'code.txt').replace(/[^a-zA-Z0-9._-]/g, '_');
    const blob = new Blob([code], { type: mimeType(language) });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.hidden = true;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function securePreviewDocument(code) {
    const document = new DOMParser().parseFromString(code, 'text/html');
    for (const link of document.querySelectorAll('a[href],area[href]')) {
        link.setAttribute('href', previewFragmentHref(link.getAttribute('href')));
    }
    // Parse before inserting metadata: comments containing <head> cannot move
    // the trusted CSP outside the actual head. Parsed scripts remain inert here.
    document.head.insertAdjacentHTML('afterbegin', PREVIEW_METADATA + PREVIEW_NAVIGATION);
    return `<!doctype html>${document.documentElement.outerHTML}`;
}

export function previewFragmentHref(href) {
    const value = String(href ?? '').trim();
    return value.startsWith('#') ? `about:srcdoc${value}` : String(href ?? '');
}

function documentTitle(code) {
    return code.match(/<title[^>]*>([^<]{1,100})<\/title>/i)?.[1]?.trim() ?? '';
}

function mimeType(language) {
    return ({
        html: 'text/html;charset=utf-8', css: 'text/css;charset=utf-8',
        javascript: 'text/javascript;charset=utf-8', json: 'application/json;charset=utf-8',
        svg: 'image/svg+xml;charset=utf-8',
    })[language] ?? 'text/plain;charset=utf-8';
}

function temporaryLabel(button, label) {
    const previous = button.textContent;
    button.textContent = label;
    window.setTimeout(() => { button.textContent = previous; }, 1400);
}

function announce(artifact, message) {
    const status = artifact.querySelector('[data-code-status]');
    if (status) status.textContent = message;
}
