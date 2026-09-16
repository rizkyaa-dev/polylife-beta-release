// Manual browser regression using the real preview module, not standalone HTML.
import { readFile, mkdir } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import path from 'node:path';
import assert from 'node:assert/strict';

const [playwrightEntry, executablePath, outputDirectory, artifactPath] = process.argv.slice(2);
const { chromium } = await import(pathToFileURL(path.resolve(playwrightEntry)).href);
const module = await readFile('resources/js/ai/code-artifacts.js', 'utf8');
const fixture = artifactPath ? await readFile(artifactPath, 'utf8') : `<!doctype html><!-- misleading <head> --><html><head><title>Kopi Senja</title><style>
body{font:18px system-ui;background:#faf1e5;color:#352014}nav{position:sticky;top:0;background:#faf1e5;padding:20px}section{min-height:900px;padding:30px}
</style></head><body><nav><a href="#menu">Menu</a> <a href="#kontak">Kontak</a></nav><section>Hero coffee shop</section><section id="menu"><h1>Menu Kopi</h1></section><section id="kontak"><h1>Kontak Kedai</h1></section>
<script>document.addEventListener('DOMContentLoaded',()=>{const a=document.createElement('a');a.id='dynamic';a.href='#kontak';a.textContent='Dynamic';document.querySelector('nav').append(a);try{parent.document.body;document.body.dataset.isolated='false'}catch{document.body.dataset.isolated='true'}});</script></body></html>`;
const escape = value => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
const harness = `<meta charset="utf-8"><main id="root"><section data-code-artifact data-code-runnable="true"><button data-code-run>Run</button><pre hidden><code>${escape(fixture)}</code></pre></section>
<aside data-code-preview hidden><strong data-code-preview-title></strong><button data-code-preview-close>Close</button><iframe data-code-preview-frame sandbox="allow-scripts" style="width:95vw;height:700px"></iframe></aside></main>
<script type="module">import {initCodeArtifacts} from '/preview.js';initCodeArtifacts(document.querySelector('#root'));</script>`;
await mkdir(outputDirectory, { recursive: true });
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: true });
try {
    const context = await browser.newContext({ viewport: { width: 1100, height: 850 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
    let applicationNavigations = 0;
    await context.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/preview.js') return route.fulfill({ contentType: 'text/javascript', body: module });
        if (url.pathname === '/workspace') {
            applicationNavigations++;
            return route.fulfill({ contentType: 'text/html', body: applicationNavigations === 1 ? harness : '<h1>LOGIN POLYLIFE</h1>' });
        }
        return route.abort();
    });
    const page = await context.newPage();
    await page.goto('https://preview-regression.invalid/workspace');
    await page.getByRole('button', { name: 'Run', exact: true }).click();
    const frame = page.frameLocator('iframe');
    await frame.getByRole('link', { name: 'Menu', exact: true }).click();
    await frame.locator('#menu').waitFor();
    const child = page.frames().find(frame => frame !== page.mainFrame());
    await child.waitForFunction(() => location.hash === '#menu', null, { timeout: 5000 });
    assert.equal(await child.evaluate(() => location.href), 'about:srcdoc#menu');
    await child.waitForFunction(() => document.getElementById('menu').getBoundingClientRect().top < 120, null, { timeout: 5000, polling: 100 });
    await page.screenshot({ path: path.join(outputDirectory, 'menu.png') });
    if (artifactPath) {
        await child.evaluate(() => {
            const link = document.createElement('a'); link.id = 'dynamic'; link.href = '#kontak'; link.textContent = 'Dynamic';
            document.querySelector('nav').append(link);
            try { parent.document.body; document.body.dataset.isolated = 'false'; }
            catch { document.body.dataset.isolated = 'true'; }
        });
    }
    await frame.getByRole('link', { name: 'Dynamic', exact: true }).click();
    await child.waitForFunction(() => location.hash === '#kontak', null, { timeout: 5000 });
    assert.equal(await child.evaluate(() => location.href), 'about:srcdoc#kontak');
    assert.equal(await child.evaluate(() => document.body.dataset.isolated), 'true');
    assert.equal(await child.evaluate(() => document.querySelector('[http-equiv="Content-Security-Policy"]').content.includes("base-uri 'none'")), true);
    assert.equal(applicationNavigations, 1);
    assert.equal(await page.locator('iframe').getAttribute('sandbox'), 'allow-scripts');
    assert.equal(await page.locator('code').textContent(), fixture);
    await page.getByRole('button', { name: 'Close', exact: true }).click();
    assert.equal(await page.locator('[data-code-preview]').isVisible(), false);
    console.log(JSON.stringify({ staticAnchor: 'pass', dynamicAnchor: 'pass', parentIsolation: 'pass', csp: 'unchanged', applicationNavigations, originalCode: 'unchanged', close: 'pass' }));
    await context.close();
} finally { await browser.close(); }
