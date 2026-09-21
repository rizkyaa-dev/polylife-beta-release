import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, mkdir, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { randomBytes } from 'node:crypto';
import path from 'node:path';

const baseline = process.argv.includes('--baseline');
const isolation = process.argv.includes('--read-committed') ? 'READ COMMITTED' : 'REPEATABLE READ';
const directory = await mkdtemp(path.join(tmpdir(), 'polylife-science-admission-'));
const database = `polylife_science_test_${randomBytes(8).toString('hex')}`;
const limit = baseline ? 1 : 4;
const users = baseline ? 2 : 16;
const env = { ...process.env, APP_ENV: 'local', DB_CONNECTION: 'mysql', DB_DATABASE: database, DB_URL: '',
    SCIENCE_ADMISSION_DATABASE: database, SCIENCE_ADMISSION_DIRECTORY: directory,
    SCIENCE_ADMISSION_LIMIT: String(limit), SCIENCE_ADMISSION_BARRIER: baseline ? '1' : '0',
    SCIENCE_ADMISSION_ISOLATION: isolation,
    CACHE_STORE: 'array', LOG_CHANNEL: 'stderr',
    ...Object.fromEntries(['CONFIG', 'ROUTES', 'SERVICES', 'PACKAGES', 'EVENTS'].map(name =>
        [`APP_${name}_CACHE`, path.relative(process.cwd(), path.join(directory, `${name.toLowerCase()}.php`))])),
};
const execute = promisify(execFile);
const invoke = async (...args) => JSON.parse((await execute('php', ['tests/Support/science-admission-runtime.php', ...args.map(String)],
    { env, windowsHide: true, timeout: 30000, maxBuffer: 2 * 1024 * 1024 })).stdout);
let report;
try {
    const initialized = await invoke('init', users);
    assert.equal(initialized.initialized, true);
    const results = await Promise.all(initialized.users.map(user => invoke('admit', user)));
    const state = await invoke('inspect');
    const admitted = results.filter(result => result.status === 'admitted').length;
    assert.equal(admitted, baseline ? isolation === 'READ COMMITTED' ? 2 : 1 : limit, JSON.stringify({ fixture_database: database, results }));
    if (baseline && isolation === 'REPEATABLE READ') {
        assert.equal(results.filter(result => result.status === 'error' && result.message.includes('SQLSTATE[40001]')).length, 1);
    } else {
        assert.equal(results.filter(result => result.status === 'error').length, 0, JSON.stringify(results));
    }
    assert.equal(state.running, admitted);
    assert.equal(state.sessions, admitted, 'Rejected admissions must roll back new sessions');
    assert.equal(state.messages, admitted, 'Rejected admissions must roll back messages');
    report = { status: baseline ? isolation === 'READ COMMITTED' ? 'race_reproduced' : 'deadlock_reproduced' : 'passed', backend: 'local MySQL protocol, independent PHP processes', server_version: initialized.server_version, isolation,
        fixture_database: database, limit, concurrent_requests: users, admitted,
        rejected: results.filter(result => result.status === 'capacity_rejected').length, state, results,
        limitation: 'Admission correctness only; not inference throughput or thousand-user capacity.' };
} finally {
    try {
        await invoke('drop');
        if (report) report.fixture_cleaned = true;
    } finally {
        const resolvedDirectory = path.resolve(directory);
        const resolvedTemp = `${path.resolve(tmpdir())}${path.sep}`;
        if (!resolvedDirectory.startsWith(resolvedTemp) || !path.basename(resolvedDirectory).startsWith('polylife-science-admission-')) {
            throw new Error('Refusing to remove an unexpected admission fixture directory.');
        }
        await rm(resolvedDirectory, { recursive: true, force: true });
    }
}
const output = 'storage/app/private/ai-science-review-20260917';
await mkdir(output, { recursive: true });
await writeFile(path.join(output, `${baseline ? 'admission-before' : 'admission-concurrency'}-${isolation.toLowerCase().replaceAll(' ', '-')}.json`), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
