// Real child termination and elapsed leases, not mocked crashes or advanced clocks.
import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';

const temporary = await mkdtemp(path.join(tmpdir(), 'polylife-science-e2e-'));
const database = path.join(temporary, 'science.sqlite');
await writeFile(database, '');
await mkdir(path.join(temporary, 'views'));
const runtime = path.resolve('tests/Support/science-e2e-runtime.php');
const env = { ...process.env, APP_ENV: 'local', APP_KEY: `base64:${Buffer.alloc(32, 42).toString('base64')}`,
    SCIENCE_E2E_DATABASE: database, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
    DB_FOREIGN_KEYS: 'true', CACHE_STORE: 'database', QUEUE_CONNECTION: 'database',
    AI_QUEUE_CONNECTION: 'database', LOG_CHANNEL: 'stderr',
    ...Object.fromEntries(['CONFIG', 'ROUTES', 'SERVICES', 'PACKAGES', 'EVENTS'].map(name =>
        [`APP_${name}_CACHE`, path.relative(process.cwd(), path.join(temporary, `${name.toLowerCase()}.php`))])),
};
const execute = promisify(execFile);
const invoke = async (...args) => JSON.parse((await execute('php', [runtime, ...args.map(String)],
    { env, windowsHide: true, timeout: 30000, maxBuffer: 4 * 1024 * 1024 })).stdout);
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let child;
const report = { status: 'in_progress', backend: 'fresh SQLite WAL / real PHP processes / database queue',
    inference: 'deterministic fixture, not provider quality evidence', temporary, cases: [] };
try {
    assert.equal((await invoke('init')).initialized, true);
    const prepared = await invoke('crash-prepare');
    child = spawn('php', [runtime, 'crash-hold', String(prepared.ticket_id)],
        { env, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    let stderr = '';
    child.stderr.on('data', chunk => { if (stderr.length < 16000) stderr += chunk; });
    const exited = new Promise((resolve, reject) => {
        child.once('error', reject);
        child.once('exit', (code, signal) => resolve({ code, signal }));
    });
    const claim = await new Promise((resolve, reject) => {
        let stdout = '';
        const timer = setTimeout(() => reject(new Error(`Claimed child did not report readiness: ${stderr}`)), 15000);
        child.stdout.on('data', chunk => {
            stdout += chunk;
            if (!stdout.includes('\n')) return;
            clearTimeout(timer);
            try { resolve(JSON.parse(stdout.slice(0, stdout.indexOf('\n')))); } catch (error) { reject(error); }
        });
        child.once('error', error => { clearTimeout(timer); reject(error); });
        child.once('exit', code => { clearTimeout(timer); reject(new Error(`Child exited before kill: ${code} ${stderr}`)); });
    });
    assert.equal(claim.claimed, true);
    assert.ok(child.exitCode === null && child.signalCode === null, 'Claim holder must be confirmed live before kill');
    assert.equal(child.kill('SIGKILL'), true);
    report.killed_process_exit = await exited;
    let state = await invoke('inspect');
    assert.equal(state.executions[0].status, 'running_server');
    assert.equal(state.executions[0].server_attempts, 1);
    assert.equal((await invoke('crash-recover')).recovered, 0, 'Live lease cannot be stolen');
    report.cases.push('real-claimed-child-killed-with-persisted-lease');
    const remaining = Date.parse(claim.lease_expires_at) - Date.now() + 250;
    assert.ok(Number.isFinite(remaining) && remaining > 0 && remaining < 46000);
    console.log(`Confirmed child terminated; waiting ${remaining}ms for its real lease.`);
    await pause(remaining);
    assert.equal((await invoke('crash-recover')).recovered, 1);
    // Independent duplicate deliveries compete for the same persisted ticket.
    await Promise.all([invoke('crash-compute', prepared.ticket_id), invoke('crash-compute', prepared.ticket_id)]);
    state = await invoke('inspect');
    assert.equal(state.executions[0].status, 'ready');
    assert.equal(state.executions[0].server_attempts, 2, 'Only one successor may claim');
    await invoke('crash-stale-failure', prepared.ticket_id, claim.claim_token);
    assert.equal((await invoke('inspect')).runs[0].status, 'running');
    report.cases.push('expired-lease-recovered-with-one-successor-and-stale-failure-fenced');
    await invoke('crash-drain');
    state = await invoke('inspect');
    assert.equal(state.runs[0].status, 'completed');
    assert.equal(state.executions[0].status, 'completed');
    assert.equal(state.queued_jobs, 0);
    assert.equal(state.failed_jobs, 0);
    assert.equal(state.calls.filter(call => call.stage === 'planner').length, 1);
    assert.equal(state.calls.filter(call => call.stage === 'main').length, 2);
    const actual = state.steps[0].result.final_state;
    const alpha = 0.2;
    const frequency = Math.sqrt(4 - alpha ** 2);
    const expected = [Math.exp(-1) * (Math.cos(5 * frequency) + alpha / frequency * Math.sin(5 * frequency)),
        -4 / frequency * Math.exp(-1) * Math.sin(5 * frequency)];
    actual.forEach((value, i) => assert.ok(Math.abs(value - expected[i]) < 1e-7));
    report.cases.push('database-queue-drained-with-one-final-reply-and-independent-oscillator-oracle');
    report.state = state;
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = error.stack;
    throw error;
} finally {
    if (child && child.exitCode === null && child.signalCode === null) child.kill('SIGKILL');
    const output = 'storage/app/private/ai-science-review-20260917';
    await mkdir(output, { recursive: true });
    await writeFile(path.join(output, 'crash-report.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report, null, 2));
}
