import { postJson } from '../transport.js';
import { SCIENCE_KERNEL_VERSION } from './contract.js';

/** A lost callback response retries the same submission, never repeats computation. */
export class ScienceCoordinator {
    constructor({ post = postJson, clientId = globalThis.crypto?.randomUUID?.(), origin = globalThis.location?.origin,
        runnerFactory = async () => { const { ScienceRunner } = await import('./runner.js'); return new ScienceRunner(); },
        cacheFactory = async () => { const { ScienceResultCache } = await import('./cache.js'); return new ScienceResultCache(); },
        requestApproval = async () => false, kernelEnabled = true, autoExecute = false,
        clientRunnerFactory = async () => { const { ClientComputationRunner } = await import('./client/runner.js'); return new ClientComputationRunner(); } } = {}) {
        this.post = post;
        this.clientId = clientId;
        this.origin = origin;
        this.runnerFactory = runnerFactory;
        this.cacheFactory = cacheFactory;
        this.requestApproval = requestApproval;
        this.kernelEnabled = kernelEnabled;
        this.autoExecute = autoExecute;
        this.clientRunnerFactory = clientRunnerFactory;
        this.clientRunner = null;
        this.runner = null;
        this.cache = null;
        this.cacheUnavailable = false;
        this.active = null;
        this.pendingSubmission = null;
        this.disposed = false;
        this.ignoredId = null;
    }

    observe(offer) {
        if (this.disposed || !offer || offer.id === this.ignoredId || !Number.isInteger(offer.id) || !this.clientId || this.active) return;
        const controller = new AbortController();
        const task = { id: offer.id, controller };
        this.active = task;
        void this.process(offer, controller.signal).catch(error => {
            if (error.httpStatus === 409) {
                this.ignoredId = offer.id;
                this.pendingSubmission = null;
            }
            // HTTP observation failures are not run failures. Lease expiry owns fallback.
        }).finally(() => { if (this.active === task) this.active = null; });
    }

    async process(offer, signal) {
        if (this.pendingSubmission?.id === offer.id) {
            await this.post(this.pendingSubmission.url, this.pendingSubmission.body);
            this.pendingSubmission = null;
            this.ignoredId = offer.id;
            return;
        }
        this.requireSameOrigin(offer.claim_url);
        const claim = await this.post(offer.claim_url, { client_id: this.clientId });
        if (signal.aborted) return;
        if (claim.id !== offer.id || !Number.isInteger(claim.attempt) || claim.attempt < 1 || typeof claim.token !== 'string' || claim.token.length !== 48
            || typeof claim.submit_url !== 'string') throw new Error('Invalid scientific execution claim.');
        this.requireSameOrigin(claim.submit_url);
        const body = { attempt: claim.attempt, token: claim.token };
        try {
            if (claim.execution_mode === 'client_script') {
                const { validateProgram } = await import('./client/contract.js');
                validateProgram(claim.program);
                const authorized = this.autoExecute && claim.auto_execute === true
                    ? true : await this.requestApproval(claim, signal);
                if (!authorized) {
                    body.failure = 'cancelled';
                } else {
                    if (signal.aborted) return;
                    this.clientRunner ??= await this.clientRunnerFactory();
                    if (signal.aborted) { this.clientRunner.dispose(); return; }
                    body.result = await this.clientRunner.run(claim.program, { signal });
                }
            } else if (!this.kernelEnabled) {
                body.failure = 'unavailable';
            } else if (claim.kernel_version !== SCIENCE_KERNEL_VERSION) {
                body.failure = 'version_mismatch';
            } else {
                if (typeof claim.cache_scope === 'string' && /^[a-f0-9]{64}$/.test(claim.cache_scope)) {
                    try {
                        if (!this.cacheUnavailable) this.cache ??= await this.cacheFactory();
                        body.result = await this.cache?.get(claim.cache_scope, claim.solver, claim.inputs);
                    } catch { this.cacheUnavailable = true; this.cache = null; }
                }
                if (!body.result) {
                    this.runner ??= await this.runnerFactory();
                    if (signal.aborted) { this.runner.dispose(); return; }
                    body.result = await this.runner.run(claim.solver, claim.inputs, { signal });
                    try { await this.cache?.put(claim.cache_scope, claim.solver, claim.inputs, body.result); } catch { /* cache is optional */ }
                }
            }
        } catch (error) {
            const allowed = ['timeout', 'unavailable', 'invalid_input', 'execution_failed', 'syntax_error', 'version_mismatch', 'cancelled'];
            body.failure = allowed.includes(error.code) ? error.code : 'unavailable';
        }
        if (signal.aborted) return;
        this.pendingSubmission = { id: offer.id, url: claim.submit_url, body };
        await this.post(claim.submit_url, body);
        this.pendingSubmission = null;
        this.ignoredId = offer.id;
    }

    requireSameOrigin(value) {
        if (typeof value !== 'string' || !this.origin) throw new Error('Invalid scientific callback URL.');
        const url = new URL(value, this.origin);
        if (url.origin !== this.origin || !['http:', 'https:'].includes(url.protocol) || url.username || url.password) {
            throw new Error('Scientific callbacks must stay on the application origin.');
        }
    }

    dispose() {
        this.disposed = true;
        this.active?.controller.abort();
        this.runner?.dispose();
        this.clientRunner?.dispose();
        this.pendingSubmission = null;
    }
}
