import assert from 'node:assert/strict';
import test from 'node:test';
import { ScienceCoordinator } from '../../resources/js/ai/science/coordinator.js';
import { SCIENCE_KERNEL_VERSION } from '../../resources/js/ai/science/contract.js';

const origin = 'https://polylife.test';
const offer = { id: 7, claim_url: `${origin}/science/7/claim` };
const claim = { id: 7, attempt: 1, token: 't'.repeat(48), kernel_version: SCIENCE_KERNEL_VERSION,
    solver: 'linear_system', inputs: { matrix: [[2]], rhs: [4] }, submit_url: `${origin}/science/7/submit` };
const computed = { status: 'computed', solution: [2], kernel_version: SCIENCE_KERNEL_VERSION };
const flush = () => new Promise(resolve => setImmediate(resolve));
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; };

test('browser-only policy refuses stale registered claims without loading a kernel or cache', async () => {
    const submissions = [];
    const coordinator = new ScienceCoordinator({ origin, clientId: 'client-one', kernelEnabled: false,
        runnerFactory: () => { throw new Error('Kernel must not load.'); },
        cacheFactory: () => { throw new Error('Kernel cache must not load.'); },
        post: async (url, body) => { if (url.endsWith('/claim')) return claim; submissions.push(body); } });
    await coordinator.process(offer, new AbortController().signal);
    assert.deepEqual(submissions, [{ attempt: 1, token: claim.token, failure: 'unavailable' }]);
});

function harness({ post, runner } = {}) {
    const requests = [];
    const runs = [];
    const factories = [];
    const engine = runner ?? { run: async (...args) => { runs.push(args); return computed; }, dispose() { this.disposed = true; } };
    const coordinator = new ScienceCoordinator({ origin, clientId: 'client-one',
        post: async (url, body) => { requests.push({ url, body }); return post ? post(url, body, requests.length) : url.endsWith('/claim') ? claim : { status: 'accepted' }; },
        runnerFactory: async () => { factories.push(true); return engine; } });
    return { coordinator, requests, runs, factories, engine };
}

test('single observed ticket computes once and ignores stale offers after acceptance', async () => {
    const { coordinator, requests, runs, factories } = harness();
    coordinator.observe(offer);
    coordinator.observe(offer);
    await flush();
    coordinator.observe(offer);
    await flush();
    assert.equal(requests.length, 2);
    assert.equal(runs.length, 1);
    assert.equal(factories.length, 1);
    assert.deepEqual(requests[1].body, { attempt: 1, token: claim.token, result: computed });
    assert.equal(coordinator.active, null);
});

test('owner-scoped cache hit skips browser computation but remains a submitted candidate', async () => {
    const cached = { ...computed, solution: [2] };
    const calls = [];
    const cache = { async get(...args) { calls.push(['get', ...args]); return cached; }, async put() { throw new Error('Cache hit must not write.'); } };
    const cachedClaim = { ...claim, cache_scope: 'a'.repeat(64) };
    const requests = [];
    const runner = { async run() { throw new Error('Cache hit must not compute.'); }, dispose() {} };
    const coordinator = new ScienceCoordinator({ origin, clientId: 'client-one', runnerFactory: async () => runner,
        cacheFactory: async () => cache, post: async (url, body) => {
            requests.push({ url, body });
            return url.endsWith('/claim') ? cachedClaim : { status: 'accepted' };
        } });
    coordinator.observe(offer);
    await flush();
    await flush();
    assert.equal(calls.length, 1);
    assert.deepEqual(requests[1].body.result, cached);
});

test('cache failures fall back to one computation and never fail submission', async () => {
    const calls = [];
    const cache = { async get() { throw new Error('Storage unavailable'); }, async put() { calls.push('put'); } };
    const cachedClaim = { ...claim, cache_scope: 'b'.repeat(64) };
    const { coordinator, requests, runs } = harness({ post: async url => url.endsWith('/claim') ? cachedClaim : { status: 'accepted' } });
    coordinator.cacheFactory = async () => cache;
    coordinator.observe(offer);
    await flush();
    await flush();
    assert.equal(runs.length, 1);
    assert.deepEqual(requests[1].body.result, computed);
    assert.deepEqual(calls, []);
});

test('lost callback acceptance response replays exactly the same submission without recomputing', async () => {
    const { coordinator, requests, runs } = harness({ post: async (url, body, number) => {
        if (number === 2) throw new Error('Network response lost after server acceptance.');
        return url.endsWith('/claim') ? claim : { status: 'accepted' };
    } });
    coordinator.observe(offer);
    await flush();
    assert.ok(coordinator.pendingSubmission);
    coordinator.observe(offer);
    await flush();
    assert.equal(requests.length, 3);
    assert.equal(requests[1].body, requests[2].body);
    assert.equal(requests[1].url, requests[2].url);
    assert.equal(runs.length, 1);
    assert.equal(coordinator.pendingSubmission, null);
});

test('lost claim response permits idempotent claim retry without starting a worker', async () => {
    const { coordinator, requests, runs } = harness({ post: async (url, body, number) => {
        if (number === 1) throw new Error('Claim response lost.');
        return url.endsWith('/claim') ? claim : { status: 'accepted' };
    } });
    coordinator.observe(offer);
    await flush();
    assert.equal(runs.length, 0);
    coordinator.observe(offer);
    await flush();
    assert.deepEqual(requests[0], requests[1]);
    assert.equal(runs.length, 1);
});

test('ownership conflicts are not repeatedly claimed by another tab', async () => {
    const { coordinator, requests, factories } = harness({ post: async () => { throw Object.assign(new Error('Already claimed'), { httpStatus: 409 }); } });
    coordinator.observe(offer);
    await flush();
    coordinator.observe(offer);
    await flush();
    assert.equal(requests.length, 1);
    assert.equal(factories.length, 0);
});

test('kernel skew and unavailable browser submit bounded fallback reasons, not invented answers', async () => {
    const skew = harness({ post: async url => url.endsWith('/claim') ? { ...claim, kernel_version: 'old' } : { status: 'accepted' } });
    skew.coordinator.observe(offer);
    await flush();
    assert.equal(skew.factories.length, 0);
    assert.deepEqual(skew.requests[1].body, { attempt: 1, token: claim.token, failure: 'version_mismatch' });
    const unavailable = harness({ runner: { run: async () => { throw new Error('Unsupported browser'); }, dispose() {} } });
    unavailable.coordinator.observe(offer);
    await flush();
    assert.deepEqual(unavailable.requests[1].body, { attempt: 1, token: claim.token, failure: 'unavailable' });
});

test('dispose while factory loads terminates the late runner and never submits', async () => {
    const factory = deferred();
    let disposed = false;
    const { coordinator, requests } = harness();
    coordinator.runnerFactory = () => factory.promise;
    coordinator.observe(offer);
    await flush();
    coordinator.dispose();
    factory.resolve({ dispose: () => { disposed = true; }, run: () => { throw new Error('Disposed runner must not run'); } });
    await flush();
    assert.equal(disposed, true);
    assert.equal(requests.length, 1);
    assert.equal(coordinator.active, null);
});

test('cancellation releases active browser compute and ignores a late result', async () => {
    const computation = deferred();
    let workerSignal;
    let disposed = 0;
    const { coordinator, requests } = harness({ runner: {
        run: (solver, inputs, { signal }) => { workerSignal = signal; return computation.promise; },
        dispose: () => { disposed++; },
    } });
    coordinator.observe(offer);
    await flush();
    assert.equal(workerSignal.aborted, false);
    coordinator.dispose();
    assert.equal(workerSignal.aborted, true);
    assert.equal(disposed, 1);
    computation.resolve(computed);
    await flush();
    coordinator.observe(offer);
    await flush();
    assert.equal(requests.length, 1, 'Cancelled compute must neither submit nor restart');
    assert.equal(coordinator.pendingSubmission, null);
    assert.equal(coordinator.active, null);
});

test('cross-origin claims and callbacks cannot receive client identifiers or claim tokens', async () => {
    const first = harness();
    first.coordinator.observe({ ...offer, claim_url: 'https://attacker.test/claim' });
    await flush();
    assert.equal(first.requests.length, 0);
    const second = harness({ post: async () => ({ ...claim, submit_url: 'https://attacker.test/submit' }) });
    second.coordinator.observe(offer);
    await flush();
    assert.equal(second.requests.length, 1);
    assert.equal(second.factories.length, 0);
});

test('malformed claims never authorize computation or submission', async () => {
    for (const broken of [{ ...claim, id: 8 }, { ...claim, attempt: 0 }, { ...claim, token: 'bad' }, { ...claim, submit_url: 'javascript:alert(1)' }]) {
        const { coordinator, requests, factories } = harness({ post: async () => broken });
        coordinator.observe(offer);
        await flush();
        assert.equal(requests.length, 1);
        assert.equal(factories.length, 0);
    }
});
