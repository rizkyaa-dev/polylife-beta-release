// Manual real-browser regression, including the production Vite worker bundle.
import assert from 'node:assert/strict';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import { pathToFileURL } from 'node:url';
import path from 'node:path';
import { build } from 'vite';
import { SCIENCE_KERNEL_VERSION } from '../../resources/js/ai/science/contract.js';

const [playwrightEntry, executablePath, outputDirectory] = process.argv.slice(2);
if (!playwrightEntry || !executablePath || !outputDirectory) throw new Error('Provide Playwright entry, Chrome executable and evidence directory.');
const { chromium } = await import(pathToFileURL(path.resolve(playwrightEntry)).href);
const fixtures = JSON.parse(await readFile('tests/Fixtures/science-kernel.json', 'utf8'));
const bundle = await build({ configFile: false, logLevel: 'silent', worker: { format: 'es' }, build: {
    write: false, minify: true,
    lib: { entry: { 'science-runner': path.resolve('resources/js/ai/science/runner.js'),
        'science-coordinator': path.resolve('resources/js/ai/science/coordinator.js'),
        'science-cache': path.resolve('resources/js/ai/science/cache.js') },
    formats: ['es'], fileName: (format, name) => `${name}.js` },
} });
const output = (Array.isArray(bundle) ? bundle : [bundle]).flatMap(result => result.output);
const entry = output.find(item => item.type === 'chunk' && item.isEntry && item.name === 'science-runner');
const coordinatorEntry = output.find(item => item.type === 'chunk' && item.isEntry && item.name === 'science-coordinator');
const cacheEntry = output.find(item => item.type === 'chunk' && item.isEntry && item.name === 'science-cache');
assert.ok(entry, 'Built runner entry is missing');
assert.ok(coordinatorEntry, 'Built coordinator entry is missing');
assert.ok(cacheEntry, 'Built cache entry is missing');
assert.ok(output.some(item => /worker-.*\.js$/.test(item.fileName)), 'Built worker asset is missing');
const assets = new Map(output.map(item => [`/${item.fileName}`, item.code ?? item.source]));
const contentSecurityPolicy = "default-src 'none'; script-src 'self'; worker-src 'self'; connect-src 'self'; style-src 'unsafe-inline'; base-uri 'none'";
const harness = `<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Science Worker regression</title>
<style>body{margin:0;padding:24px;background:#0f172a;color:#e2e8f0;font:16px system-ui}main{max-width:850px;margin:auto}h1{font-size:24px;color:#c7d2fe}li{margin:12px 0;overflow-wrap:anywhere}button{background:#4f46e5;color:white;border:0;padding:12px;border-radius:12px}p{color:#94a3b8}</style>
<main><h1>Science Worker — browser evidence</h1><p>Trusted numerical kernel; loopback callbacks only. This is a test harness, not the PolyLife chat UI.</p><button id="interactive">UI clicks: 0</button><ol id="results"></ol></main><script type="module" src="/harness.js"></script></html>`;
assets.set('/harness.js', `import {ScienceRunner} from '/${entry.fileName}';
import {ScienceCoordinator} from '/${coordinatorEntry.fileName}';
import {ScienceResultCache} from '/${cacheEntry.fileName}';
window.scienceRunner=new ScienceRunner();window.scienceFixtures=${JSON.stringify(fixtures)};
window.ScienceResultCache=ScienceResultCache;window.ScienceCoordinator=ScienceCoordinator;window.ScienceRunner=ScienceRunner;
window.coordinatorSolveCalls=0;window.scienceCoordinator=new ScienceCoordinator({runnerFactory:async()=>{
const runner=new ScienceRunner();return {run:(...args)=>{window.coordinatorSolveCalls++;return runner.run(...args)},dispose:()=>runner.dispose()};}});
let clicks=0;document.querySelector('#interactive').addEventListener('click',()=>{document.querySelector('#interactive').textContent='UI clicks: '+(++clicks)});
window.showResult=(name,status)=>{const li=document.createElement('li');li.textContent=name+': '+status;document.querySelector('#results').append(li)};
window.scienceReady=true;`);
const callbacks = { claims: 0, submissions: 0, accepted_bodies: [] };
const server = createServer(async (request, response) => {
    const pathname = new URL(request.url, 'http://localhost').pathname;
    response.setHeader('Content-Security-Policy', contentSecurityPolicy);
    response.setHeader('X-Content-Type-Options', 'nosniff');
    if (request.method === 'POST' && ['/science/7/claim', '/science/7/submit'].includes(pathname)) {
        const chunks = [];
        for await (const chunk of request) chunks.push(chunk);
        const raw = Buffer.concat(chunks).toString('utf8');
        response.setHeader('Content-Type', 'application/json');
        if (pathname.endsWith('/claim')) {
            callbacks.claims++;
            response.end(JSON.stringify({ id: 7, attempt: 1, token: 't'.repeat(48),
                kernel_version: SCIENCE_KERNEL_VERSION, solver: 'linear_system', inputs: { matrix: [[2]], rhs: [4] },
                submit_url: '/science/7/submit' }));
        } else {
            callbacks.submissions++;
            if (!callbacks.accepted_bodies.includes(raw)) callbacks.accepted_bodies.push(raw);
            // The server accepted the result, but the first HTTP response is lost.
            response.writeHead(callbacks.submissions === 1 ? 503 : 202);
            response.end(JSON.stringify({ status: callbacks.submissions === 1 ? 'error' : 'accepted' }));
        }
        return;
    }
    if (pathname === '/') { response.setHeader('Content-Type', 'text/html'); response.end(harness); return; }
    if (assets.has(pathname)) { response.setHeader('Content-Type', 'text/javascript'); response.end(assets.get(pathname)); return; }
    response.writeHead(404); response.end();
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
let browser;
try {
    await mkdir(outputDirectory, { recursive: true });
    browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: true });
    const context = await browser.newContext({ viewport: { width: 1100, height: 850 }, serviceWorkers: 'block' });
    const page = await context.newPage();
    const errors = [];
    const requests = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => requests.push(request.url()));
    await page.goto(origin);
    await page.waitForFunction(() => window.scienceReady === true);
    const results = await page.evaluate(async () => {
        const output = [];
        for (const fixture of window.scienceFixtures) {
            const start = performance.now();
            const result = await window.scienceRunner.run(fixture.solver, fixture.inputs);
            output.push({ name: fixture.name, duration_ms: performance.now() - start, result });
            window.showResult(fixture.name, result.status);
        }
        const controller = new AbortController();
        const pending = window.scienceRunner.run('linear_system', { matrix: [[2]], rhs: [4] }, { signal: controller.signal });
        controller.abort();
        let cancellation;
        try { await pending; } catch (error) { cancellation = error.code; }
        const probe = await window.scienceRunner.run('linear_system', { matrix: [[2]], rhs: [4] });
        let expired;
        try { await window.scienceRunner.run('linear_system', { matrix: [[2]], rhs: [4] }, { budgetMs: 1 }); }
        catch (error) { expired = error.code; }
        window.showResult('cancel + reusable runner', cancellation);
        window.showResult('1ms deadline', expired ?? 'completed within budget');
        return { output, cancellation, recovered_solution: probe.solution, expired };
    });
    const cacheEvidence = await page.evaluate(async kernelVersion => {
        let clock = 1000;
        const cache = new window.ScienceResultCache({ now: () => clock });
        const scopeA = 'a'.repeat(64), scopeB = 'b'.repeat(64);
        const input = { matrix: [[2]], rhs: [4] };
        const result = { status: 'computed', solution: [2], kernel_version: kernelVersion };
        await cache.put(scopeA, 'linear_system', input, result);
        const hit = await cache.get(scopeA, 'linear_system', input);
        clock += 3600001;
        const expired = await cache.get(scopeA, 'linear_system', input);
        clock += 1;
        await cache.put(scopeA, 'linear_system', input, result);
        const otherOwner = await cache.get(scopeB, 'linear_system', input);
        for (let i = 0; i < 40; i++) await cache.put(scopeB, 'linear_system', { matrix: [[i + 1]], rhs: [i] }, result);
        const db = await new Promise((resolve, reject) => { const open = indexedDB.open('polylife-ai-science-results', 1);
            open.onsuccess = () => resolve(open.result); open.onerror = () => reject(open.error); });
        const count = await new Promise((resolve, reject) => { const request = db.transaction('entries').objectStore('entries').count();
            request.onsuccess = () => resolve(request.result); request.onerror = () => reject(request.error); });
        return { hit, expired, other_owner: otherOwner, bounded_count: count };
    }, SCIENCE_KERNEL_VERSION);
    assert.deepEqual(cacheEvidence.hit.solution, [2]);
    assert.equal(cacheEvidence.expired, null);
    assert.equal(cacheEvidence.other_owner, null);
    assert.ok(cacheEvidence.bounded_count <= 32);
    const coordinatorCache = await page.evaluate(async kernelVersion => {
        const scope = 'c'.repeat(64);
        const offer = { id: 19, claim_url: `${location.origin}/fake/claim` };
        const submissions = [];
        let computations = 0;
        const post = async (url, body) => {
            if (url.endsWith('/claim')) return { id: 19, attempt: 1, token: 't'.repeat(48), kernel_version: kernelVersion,
                cache_scope: scope, solver: 'linear_system', inputs: { matrix: [[2]], rhs: [4] }, submit_url: `${location.origin}/fake/submit` };
            submissions.push(structuredClone(body));
            return { status: 'accepted' };
        };
        const create = () => new window.ScienceCoordinator({ post, origin: location.origin, clientId: crypto.randomUUID(),
            runnerFactory: async () => { computations++; return new window.ScienceRunner(); } });
        await create().process(offer, new AbortController().signal);
        await create().process(offer, new AbortController().signal);
        return { computations, submissions };
    }, SCIENCE_KERNEL_VERSION);
    assert.equal(coordinatorCache.computations, 1);
    assert.equal(coordinatorCache.submissions.length, 2);
    assert.deepEqual(coordinatorCache.submissions[0].result, coordinatorCache.submissions[1].result);
    assert.equal(results.cancellation, 'cancelled');
    assert.deepEqual(results.recovered_solution, [2]);
    // A fast machine may finish within 1ms; otherwise no late answer is accepted.
    assert.ok(results.expired === undefined || results.expired === 'timeout');
    results.output.forEach((item, i) => {
        assert.equal(item.result.status, fixtures[i].expected.status, item.name);
        fixtures[i].expected.outputs?.forEach((value, j) => assert.ok(Math.abs(item.result.outputs[j].value - value) <= (fixtures[i].expected.tolerance ?? 1e-7), item.name));
        for (const field of ['solution', 'final_state']) {
            fixtures[i].expected[field]?.forEach((value, j) => assert.ok(Math.abs(item.result[field][j] - value) <= (fixtures[i].expected.tolerance ?? 1e-7), item.name));
        }
    if (fixtures[i].expected.value !== undefined) assert.ok(Math.abs(item.result.value - fixtures[i].expected.value) <= (fixtures[i].expected.tolerance ?? 1e-7));
    });
    await page.getByRole('button', { name: 'UI clicks: 0' }).click();
    assert.equal(await page.locator('#interactive').textContent(), 'UI clicks: 1');
    await page.evaluate(() => window.scienceCoordinator.observe({ id: 7, claim_url: '/science/7/claim' }));
    await page.waitForFunction(() => !window.scienceCoordinator.active && window.scienceCoordinator.pendingSubmission !== null);
    await page.evaluate(() => window.scienceCoordinator.observe({ id: 7, claim_url: '/science/7/claim' }));
    await page.waitForFunction(() => !window.scienceCoordinator.active && window.scienceCoordinator.pendingSubmission === null);
    assert.equal(await page.evaluate(() => window.coordinatorSolveCalls), 1);
    assert.equal(callbacks.claims, 1);
    assert.equal(callbacks.submissions, 2);
    assert.equal(callbacks.accepted_bodies.length, 1);
    assert.deepEqual(JSON.parse(callbacks.accepted_bodies[0]).result.solution, [2]);
    await page.evaluate(() => { window.showResult('HTTP callback lost + idempotent replay', 'one solve / one accepted result'); window.scienceCoordinator.dispose(); });
    assert.deepEqual(errors, []);
    assert.ok(requests.every(url => url.startsWith(origin)), 'Unexpected external network request');
    await page.screenshot({ path: path.join(outputDirectory, 'worker-desktop.png') });
    await page.setViewportSize({ width: 320, height: 800 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await page.screenshot({ path: path.join(outputDirectory, 'worker-mobile.png') });
    const evidence = { browser: 'Chrome headless, fresh profile', bundle: 'Vite production ES modules', csp: contentSecurityPolicy,
        cases: results.output.map(({ name, duration_ms, result }) => ({ name, duration_ms, status: result.status })),
        cancellation: results.cancellation, recovered_solution: results.recovered_solution, short_deadline: results.expired ?? 'within_budget',
        cache: cacheEvidence,
        coordinator_cache: { computations: coordinatorCache.computations, submissions: coordinatorCache.submissions.length },
        coordinator_http: { claim_requests: callbacks.claims, submission_requests: callbacks.submissions,
            accepted_unique_results: callbacks.accepted_bodies.length, computations: 1, backend: 'isolated HTTP contract fixture, not Laravel' },
        external_requests: 0, page_errors: errors, application_ui_integrated: false,
        bundle_bytes: Object.fromEntries(output.map(item => [item.fileName, Buffer.byteLength(item.code ?? item.source)])),
    };
    await writeFile(path.join(outputDirectory, 'worker-report.json'), JSON.stringify(evidence, null, 2));
    console.log(JSON.stringify(evidence, null, 2));
    await context.close();
} finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
}
