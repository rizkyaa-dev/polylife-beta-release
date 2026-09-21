// Real Laravel HTTP + database queue + production assets in a fresh browser/DB.
import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { createServer } from 'node:net';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [playwrightEntry, executablePath, outputDirectory] = process.argv.slice(2);
if (!playwrightEntry || !executablePath || !outputDirectory) throw new Error('Provide Playwright entry, Chrome executable and evidence directory.');
const { chromium } = await import(pathToFileURL(path.resolve(playwrightEntry)).href);
const temporary = await mkdtemp(path.join(tmpdir(), 'polylife-science-e2e-'));
const database = path.join(temporary, 'science.sqlite');
await writeFile(database, '');
await mkdir(path.join(temporary, 'views'));
await mkdir(outputDirectory, { recursive: true });
const reservation = createServer();
await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
const port = reservation.address().port;
await new Promise(resolve => reservation.close(resolve));
const origin = `http://127.0.0.1:${port}`;
const runtime = path.resolve('tests/Support/science-e2e-runtime.php');
const env = { ...process.env, APP_ENV: 'local', APP_URL: origin, APP_DEBUG: 'true',
    APP_KEY: `base64:${Buffer.alloc(32, 42).toString('base64')}`, SCIENCE_E2E_DATABASE: database,
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', DB_FOREIGN_KEYS: 'true',
    SESSION_DRIVER: 'database', SESSION_CONNECTION: 'sqlite', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'database', QUEUE_CONNECTION: 'database', AI_QUEUE_CONNECTION: 'database',
    LOG_CHANNEL: 'stderr', MAIL_MAILER: 'array', ASSET_URL: '',
    // Laravel cache-path normalization treats Windows drive paths as relative.
    ...Object.fromEntries(['CONFIG', 'ROUTES', 'SERVICES', 'PACKAGES', 'EVENTS'].map(name => [`APP_${name}_CACHE`, path.relative(process.cwd(), path.join(temporary, `${name.toLowerCase()}.php`))])),
};
const runPhp = mode => promisify(execFile)('php', [runtime, mode], { env, windowsHide: true, maxBuffer: 4 * 1024 * 1024 });
const children = [];
const logs = [];
let browser;
let page;
const evidence = { environment: 'fresh temporary SQLite + database queue + fresh Chrome profile',
    inference: 'deterministic fixture; no live provider correctness claim', production_assets: true,
    cases: [], application_ui_integrated: true };
function start(args) {
    const child = spawn('php', args, { env, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    children.push(child);
    for (const stream of [child.stdout, child.stderr]) stream.on('data', chunk => { if (logs.length < 1000) logs.push(chunk.toString()); });
    child.on('error', error => logs.push(error.message));
    return child;
}
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function snapshot() { return JSON.parse((await runPhp('inspect')).stdout); }
async function inspectResult(runId) {
    const state = await snapshot();
    return { run: state.runs.find(run => run.id === runId), execution: state.executions.find(ticket => ticket.run_id === runId),
        step: state.steps.find(step => step.run_id === runId), calls: state.calls.filter(call => call.run_id === runId) };
}
try {
    const initialized = await runPhp('init');
    logs.push(initialized.stderr);
    const initialization = JSON.parse(initialized.stdout);
    assert.equal(initialization.initialized, true, 'Isolated database initialization did not complete');
    assert.equal(initialization.immediate_transactions, true, 'Temporary queue DB must reserve writes before reading snapshots');
    evidence.sqlite_immediate_transactions = true;
    const server = start(['-S', `127.0.0.1:${port}`, '-t', path.resolve('public'), runtime]);
    start([runtime, 'worker']);
    let ready = false;
    for (let attempt = 0; attempt < 80; attempt++) {
        if (server.exitCode !== null) throw new Error('Isolated HTTP server exited before startup.');
        try { const response = await fetch(`${origin}/up`); if (response.ok) { ready = true; break; }
            if (response.status >= 500) throw new Error(`HTTP startup failed: ${response.status}`);
        } catch (error) { if (error.message.startsWith('HTTP startup failed:')) throw error; }
        await pause(100);
    }
    assert.ok(ready, 'Isolated HTTP server did not start');
    browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: true });
    // Playwright's serviceWorkers:block injects an unsafe navigator getter into
    // opaque sandbox frames. A fresh profile avoids stale workers without injection.
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    await context.addInitScript(() => {
        const NativeWorker = globalThis.Worker;
        globalThis.scienceWorkerEvidence = { active: 0, held: 0, terminated: 0 };
        globalThis.Worker = class extends NativeWorker {
            constructor(...args) {
                super(...args);
                this.scienceListeners = new Map();
                this.scienceTerminated = false;
                globalThis.scienceWorkerEvidence.active++;
            }
            addEventListener(type, listener, options) {
                if (type !== 'message') return super.addEventListener(type, listener, options);
                const wrapped = event => {
                    if (globalThis.holdScienceMessage) { globalThis.scienceWorkerEvidence.held++; return; }
                    listener(event);
                };
                this.scienceListeners.set(listener, wrapped);
                return super.addEventListener(type, wrapped, options);
            }
            removeEventListener(type, listener, options) {
                const wrapped = type === 'message' ? this.scienceListeners.get(listener) ?? listener : listener;
                this.scienceListeners.delete(listener);
                return super.removeEventListener(type, wrapped, options);
            }
            terminate() {
                if (!this.scienceTerminated) {
                    this.scienceTerminated = true;
                    globalThis.scienceWorkerEvidence.active--;
                    globalThis.scienceWorkerEvidence.terminated++;
                }
                return super.terminate();
            }
        };
    });
    page = await context.newPage();
    const errors = [];
    const external = [];
    const scienceRequests = [];
    evidence.page_errors = errors;
    evidence.console_errors = [];
    evidence.http_errors = [];
    page.on('console', message => { if (message.type() === 'error') evidence.console_errors.push(message.text()); });
    page.on('response', response => { if (response.status() >= 400) evidence.http_errors.push({ url: response.url(), status: response.status() }); });
    page.on('pageerror', error => errors.push(error.stack ?? error.message));
    page.on('request', request => {
        if (!request.url().startsWith(origin) && !request.url().startsWith('data:') && !request.url().startsWith('about:')) external.push(request.url());
        if (/\/science\/\d+\/(claim|submit)$/.test(request.url())) scienceRequests.push({ url: request.url(), method: request.method() });
    });
    await page.goto(`${origin}/login`);
    await page.getByLabel('Email', { exact: true }).fill('science-e2e@example.invalid');
    await page.getByLabel('Password', { exact: true }).fill('Fixture-password-42!');
    const loginResponse = page.waitForResponse(response => response.url().includes('/livewire/update') && response.request().method() === 'POST');
    await page.locator('[data-login-form] button[type="submit"]').click();
    assert.ok((await loginResponse).ok(), 'Fixture login HTTP request failed');
    await page.waitForURL(url => !url.pathname.startsWith('/login'));

    async function submit(prompt, { holdWorkerMessage = false } = {}) {
        await page.goto(`${origin}/workspace/ai?new=1`);
        if (holdWorkerMessage) await page.evaluate(() => { globalThis.holdScienceMessage = true; });
        await page.locator('[data-ai-input]').fill(prompt);
        const response = page.waitForResponse(response => response.url().endsWith('/workspace/ai/chat') && response.request().method() === 'POST');
        await page.locator('[data-ai-send]').click();
        const accepted = await (await response).json();
        assert.ok(Number.isInteger(accepted.run_id), JSON.stringify(accepted));
        return accepted;
    }
    async function completed() {
        await page.locator('[data-ai-message-list] .ai-message-assistant').filter({ hasText: 'Hasil sains:' }).last().waitFor({ timeout: 30000 });
        assert.equal(await page.locator('[data-ai-error]').isVisible(), false);
    }
    const oscillator = 'Hitung osilator teredam: m=1 kg, c=0.4 kg/s, k=4 N/m, x(0)=1 m dan v(0)=0 m/s, tanpa gaya luar. Cari x dan v pada t=5 s dengan target lokal 1e-8 dalam satuan masing-masing. Jelaskan batas verifikasinya.';
    const first = await submit(oscillator);
    await completed();
    const initial = await inspectResult(first.run_id);
    assert.equal(initial.run.status, 'completed');
    assert.equal(initial.execution.server_attempts, 1);
    assert.equal(initial.calls.filter(call => call.stage === 'planner').length, 1);
    assert.equal(initial.step.result.dimension_verification.status, 'declared_dimensions_checked');
    assert.ok(Math.abs(initial.step.result.final_state[0] + 0.33685168059041337) < 1e-7);
    assert.ok(Math.abs(initial.step.result.final_state[1] - 0.3706914139692117) < 1e-7);
    assert.ok(scienceRequests.some(request => request.url.endsWith('/submit')));
    evidence.cases.push({ name: 'oscillator-browser-server-completion', ...initial });
    await page.screenshot({ path: path.join(outputDirectory, 'chat-science-desktop.png') });

    let interruptedClaims = 0;
    const interrupt = async route => { interruptedClaims++; await route.abort('failed'); };
    await page.route('**/science/*/claim', interrupt);
    const lostClaim = page.waitForRequest(request => /\/science\/\d+\/claim$/.test(request.url()));
    const second = await submit('Hitung kerja gaya F(x)=10*x*exp(-x*x) N untuk x numerik dalam meter, dari 0 ke 2 m; satuan koefisien sesuai hukum gaya, toleransi absolut 1e-7 J.');
    await lostClaim;
    await page.unroute('**/science/*/claim', interrupt);
    await page.reload();
    await completed();
    const recovered = await inspectResult(second.run_id);
    assert.equal(recovered.run.status, 'completed');
    assert.equal(recovered.calls.filter(call => call.stage === 'planner').length, 1);
    assert.ok(Math.abs(recovered.step.result.value - 5 * (1 - Math.exp(-4))) < 1e-7);
    evidence.cases.push({ name: 'reload-after-lost-claim', interrupted_claims: interruptedClaims, ...recovered });

    // Execute the real module Worker, holding only its delivery to the coordinator.
    await page.evaluate(() => new Promise((resolve, reject) => {
        const request = indexedDB.open('polylife-ai-science-results', 1);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const database = request.result;
            const transaction = database.transaction('entries', 'readwrite');
            transaction.objectStore('entries').clear();
            transaction.onerror = () => reject(transaction.error);
            transaction.oncomplete = () => { database.close(); resolve(); };
        };
    }));
    const cancelled = await submit(oscillator, { holdWorkerMessage: true });
    await page.waitForFunction(() => scienceWorkerEvidence.active === 1 && scienceWorkerEvidence.held === 1);
    await page.evaluate(() => {
        document.querySelector('[data-ai-workspace]').addEventListener('ai:run-cancelled', () => {
            globalThis.scienceCancellationEvidence = { ...scienceWorkerEvidence };
        }, { once: true });
    });
    const cancellation = page.waitForResponse(response => /\/runs\/\d+\/cancel$/.test(response.url()));
    await page.locator('[data-ai-send]').click();
    assert.equal((await (await cancellation).json()).run_status, 'cancelled');
    await page.waitForFunction(() => globalThis.scienceCancellationEvidence?.active === 0);
    const cancellationResources = await page.evaluate(() => globalThis.scienceCancellationEvidence);
    assert.equal(cancellationResources.terminated, 1, 'Acknowledged Stop must terminate the active Worker immediately');
    await page.locator('[data-ai-form][aria-busy="false"]').waitFor({ timeout: 30000 });
    assert.equal(await page.locator('[data-ai-input]').inputValue(), oscillator);
    assert.equal(await page.locator('[data-ai-error]').isVisible(), false);
    const stopped = await inspectResult(cancelled.run_id);
    assert.equal(stopped.run.error_code, 'user_cancelled');
    assert.equal(stopped.execution.status, 'cancelled');
    evidence.cases.push({ name: 'stop-during-science', browser_resources: cancellationResources, ...stopped });

    const cross = await submit(`Buat kalkulator HTML standalone untuk ${oscillator} Waktu bisa diubah, tampilkan x dan v, tanpa dependency eksternal.`);
    await page.locator('[data-code-run]').last().waitFor({ timeout: 30000 });
    await page.locator('[data-code-run]').last().click();
    const preview = page.frameLocator('[data-code-preview-frame]');
    assert.equal(await preview.locator('body').evaluate(() => {
        try { return navigator.serviceWorker ? 'accessible' : 'unavailable'; }
        catch (error) { return error.name; }
    }), 'SecurityError', 'Generated preview must retain its opaque sandbox origin');
    assert.equal(await preview.locator('#position').textContent(), 'x = -0.3368516806 m');
    assert.equal(await preview.locator('#velocity').textContent(), 'v = 0.3706914140 m/s');
    await preview.getByLabel('Waktu (detik)').fill('0');
    assert.equal(await preview.locator('#position').textContent(), 'x = 1.0000000000 m');
    await preview.getByRole('link', { name: 'Lihat hasil' }).click();
    assert.equal(await preview.locator('h1').textContent(), 'Kalkulator osilator teredam');
    const crossResult = await inspectResult(cross.run_id);
    assert.equal(crossResult.run.status, 'completed');
    assert.equal(crossResult.calls.filter(call => call.stage === 'coder').length, 1);
    evidence.cases.push({ name: 'science-contract-coder-preview-controls', ...crossResult });
    await page.screenshot({ path: path.join(outputDirectory, 'chat-science-preview.png') });
    await page.locator('[data-code-preview-close]').click();
    await page.setViewportSize({ width: 320, height: 800 });
    await page.waitForFunction(() => getComputedStyle(document.querySelector('#app-shell')).paddingLeft === '0px');
    evidence.mobile_geometry = await page.evaluate(() => ({ width: innerWidth, scroll_width: document.documentElement.scrollWidth,
        overflowing: [...document.querySelectorAll('body *')].filter(element => { const r = element.getBoundingClientRect(); return r.width > 0 && r.right > innerWidth + 1; })
            .slice(0, 15).map(element => ({ tag: element.tagName, class: element.className, right: element.getBoundingClientRect().right })) }));
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Actual chat has mobile horizontal overflow');
    await page.screenshot({ path: path.join(outputDirectory, 'chat-science-mobile.png') });
    // Consume the real delayed fallback deliveries before asserting cancellation safety.
    let drained;
    for (let attempt = 0; attempt < 30; attempt++) {
        drained = await snapshot();
        if (drained.queued_jobs === 0) break;
        assert.ok(children.every(child => child.exitCode === null), 'HTTP/queue process exited during delayed deliveries');
        await pause(1000);
    }
    assert.equal(drained.queued_jobs, 0, 'Delayed fallback jobs did not drain');
    assert.equal(drained.failed_jobs, 0, 'Queue jobs failed');
    assert.equal(drained.runs.find(run => run.id === cancelled.run_id).error_code, 'user_cancelled');
    assert.equal(drained.executions.find(ticket => ticket.run_id === cancelled.run_id).server_attempts, 0);
    assert.equal(drained.calls.filter(call => call.run_id === cancelled.run_id && call.stage === 'main').length, 1);
    evidence.delayed_queue_drained = true;
    assert.deepEqual(errors, [], 'JavaScript runtime errors in actual application');
    evidence.page_errors = errors;
    evidence.external_requests = external;
    evidence.science_http_requests = scienceRequests;
    evidence.status = 'passed';
    await context.close();
} catch (error) {
    evidence.status = 'failed';
    evidence.failure = error.stack;
    evidence.server_log_head = logs.join('').slice(0, 16000);
    evidence.server_log_tail = logs.join('').slice(-12000);
    if (page && !page.isClosed()) {
        evidence.current_url = page.url();
        evidence.visible_text = (await page.locator('body').innerText()).slice(0, 5000);
        await page.screenshot({ path: path.join(outputDirectory, 'chat-failure.png') });
    }
    try { evidence.state = await snapshot(); } catch { /* preserve original failure */ }
    throw error;
} finally {
    if (browser) await browser.close();
    for (const child of children) if (child.exitCode === null) child.kill();
    await writeFile(path.join(outputDirectory, 'chat-report.json'), JSON.stringify(evidence, null, 2));
    console.log(JSON.stringify({ status: evidence.status, cases: evidence.cases.map(item => item.name),
        database: temporary, evidence: outputDirectory, failure: evidence.failure }, null, 2));
}
