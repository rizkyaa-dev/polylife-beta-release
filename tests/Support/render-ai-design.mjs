// Manual visual QA for generated artifacts. Never load an application login/profile.
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [artifactPath, outputDirectory, playwrightEntry, browserExecutable] = process.argv.slice(2);
if (!artifactPath || !outputDirectory || !playwrightEntry || !browserExecutable) {
    throw new Error('Usage: node render-ai-design.mjs <html> <output-dir> <playwright-entry> <browser-executable>');
}
const code = await readFile(artifactPath, 'utf8');
if (Buffer.byteLength(code) > 180_000) throw new Error('Artifact exceeds visual QA size limit.');
const policy = "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; frame-src 'none'; object-src 'none'; form-action 'none'; base-uri 'none'; worker-src 'none'";
const metadata = `<meta http-equiv="Content-Security-Policy" content="${policy}"><meta name="referrer" content="no-referrer">`;
const document = /<head\b[^>]*>/i.test(code) ? code.replace(/<head\b[^>]*>/i, match => match + metadata) : metadata + code;
const { chromium } = await import(pathToFileURL(path.resolve(playwrightEntry)).href);
await mkdir(outputDirectory, { recursive: true });
const browser = await chromium.launch({ executablePath: browserExecutable, headless: true, chromiumSandbox: true,
    args: ['--disable-background-networking', '--disable-extensions'] });
const reports = [];
try {
    for (const viewport of [{ name: 'desktop', width: 1440, height: 960 }, { name: 'mobile', width: 390, height: 844 }, { name: 'narrow', width: 320, height: 740 }]) {
        const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height },
            reducedMotion: 'reduce', serviceWorkers: 'block', acceptDownloads: false });
        const page = await context.newPage();
        page.setDefaultTimeout(5_000);
        const errors = [];
        const blocked = [];
        page.on('pageerror', error => { if (errors.length < 20) errors.push(error.message); });
        page.on('dialog', dialog => dialog.dismiss());
        context.on('page', popup => { if (popup !== page) popup.close(); });
        await context.route('**/*', async route => {
            if (route.request().url() === 'https://visual-review.invalid/' && route.request().isNavigationRequest()
                && route.request().frame() === page.mainFrame()) {
                await route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: document });
            } else {
                if (blocked.length < 20) blocked.push(route.request().url());
                await route.abort();
            }
        });
        try {
            await page.goto('https://visual-review.invalid/', { waitUntil: 'load', timeout: 10_000 });
            await page.evaluate(() => document.fonts.ready);
            const metrics = await page.evaluate(() => {
                const rgb = value => {
                    const match = value.match(/^rgba?\(([^)]+)\)$/);
                    if (!match) return null;
                    const parts = match[1].split(',').map(Number);
                    return parts.length === 3 || parts[3] === 1 ? parts.slice(0, 3) : null;
                };
                const luminance = color => {
                    const linear = color.map(channel => {
                        const c = channel / 255;
                        return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
                    });
                    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
                };
                const samples = [];
                for (const element of [...document.querySelectorAll('h1,h2,h3,p,a,button,label,small,li')].slice(0, 160)) {
                    const style = getComputedStyle(element);
                    const rect = element.getBoundingClientRect();
                    if (!rect.width || !rect.height || !element.textContent.trim() || style.visibility === 'hidden') continue;
                    const foreground = rgb(style.color);
                    let background = null;
                    let ambiguous = false;
                    for (let ancestor = element; ancestor; ancestor = ancestor.parentElement) {
                        const parentStyle = getComputedStyle(ancestor);
                        if (Number(parentStyle.opacity) < 1 || (!background && parentStyle.backgroundImage !== 'none')) { ambiguous = true; break; }
                        if (!background) {
                            const color = parentStyle.backgroundColor;
                            const alpha = color.match(/^rgba\([^,]+,[^,]+,[^,]+,\s*([\d.]+)\)$/);
                            if (alpha && Number(alpha[1]) > 0 && Number(alpha[1]) < 1) { ambiguous = true; break; }
                            background = rgb(color);
                        }
                    }
                    if (ambiguous || !foreground) continue;
                    background ??= [255, 255, 255];
                    const a = luminance(foreground);
                    const b = luminance(background);
                    const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
                    const size = parseFloat(style.fontSize);
                    const minimum = size >= 24 || (size >= 18.67 && parseInt(style.fontWeight, 10) >= 700) ? 3 : 4.5;
                    samples.push({ text: element.textContent.trim().slice(0, 60), ratio: Math.round(ratio * 100) / 100,
                        minimum, pass: ratio >= minimum });
                }
                return {
                    width: innerWidth, scrollWidth: document.documentElement.scrollWidth,
                    height: document.documentElement.scrollHeight,
                    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                    brokenImages: [...document.images].filter(image => !image.complete || !image.naturalWidth).length,
                    missingFragmentTargets: [...document.querySelectorAll('a[href^="#"]')].map(link => link.getAttribute('href'))
                        .filter(href => {
                            try { return href === '#' || !document.getElementById(decodeURIComponent(href.slice(1))); }
                            catch { return true; }
                        }),
                    buttons: [...document.querySelectorAll('button')].filter(button => button.getBoundingClientRect().width)
                        .map(button => ({ text: button.textContent.trim(), label: button.getAttribute('aria-label'), expanded: button.getAttribute('aria-expanded') })),
                    contrast: { evaluated: samples.length, failures: samples.filter(sample => !sample.pass),
                        scope: 'opaque sampled text pairs only; images, gradients, alpha and full accessibility are not certified' },
                };
            });
            await page.screenshot({ path: path.join(outputDirectory, `${viewport.name}.png`), animations: 'disabled' });
            if (metrics.height <= 6_000) {
                await page.screenshot({ path: path.join(outputDirectory, `${viewport.name}-full.png`), fullPage: true, animations: 'disabled' });
            }
            await page.keyboard.press('Tab');
            const keyboardFocus = await page.evaluate(() => {
                const active = document.activeElement;
                const style = getComputedStyle(active);
                return { tag: active.tagName, text: active.textContent.trim().slice(0, 60),
                    focusVisible: active.matches(':focus-visible'), outlineStyle: style.outlineStyle,
                    outlineWidth: style.outlineWidth };
            });
            const menus = page.locator('button[aria-expanded]');
            let menuCheck = 'not applicable';
            for (let index = 0; index < await menus.count(); index++) {
                const menu = menus.nth(index);
                if (await menu.isVisible()) {
                    const before = await menu.getAttribute('aria-expanded');
                    await menu.click();
                    const after = await menu.getAttribute('aria-expanded');
                    await page.screenshot({ path: path.join(outputDirectory, `${viewport.name}-menu.png`), animations: 'disabled' });
                    await page.keyboard.press('Escape');
                    const escape = await menu.getAttribute('aria-expanded');
                    menuCheck = { before, after, escape, toggles: before !== after, escapeCloses: escape === 'false' };
                    break;
                }
            }
            reports.push({ viewport: viewport.name, ...metrics, errors, blockedRequests: blocked, keyboardFocus, menuCheck });
        } finally {
            await context.close();
        }
    }
    await writeFile(path.join(outputDirectory, 'report.json'), JSON.stringify(reports, null, 2));
    console.log(JSON.stringify(reports));
} finally {
    await browser.close();
}
