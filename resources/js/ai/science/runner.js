import { SCIENCE_KERNEL_VERSION, SCIENCE_SCHEMA_VERSION, ScienceError } from './contract.js';
import { prepareScienceInput } from './input.js';

const defaultWorkerFactory = () => new Worker(new URL('./worker.js', import.meta.url), { type: 'module', name: 'polylife-science' });

/** One active computation per runner; no unbounded client queue or automatic retry. */
export class ScienceRunner {
    constructor({ workerFactory = defaultWorkerFactory, now = () => performance.now(),
        setTimer = (callback, delay) => globalThis.setTimeout(callback, delay), clearTimer = id => globalThis.clearTimeout(id) } = {}) {
        this.workerFactory = workerFactory;
        this.now = now;
        this.setTimer = setTimer;
        this.clearTimer = clearTimer;
        this.active = null;
        this.sequence = 0;
        this.disposed = false;
    }

    run(solver, inputs, { signal, budgetMs = 2000 } = {}) {
        if (this.disposed) return Promise.reject(new ScienceError('disposed', 'Science runner is disposed.'));
        if (this.active) return Promise.reject(new ScienceError('busy', 'A scientific computation is already running.'));
        if (signal?.aborted) return Promise.reject(new ScienceError('cancelled', 'Scientific computation cancelled.'));
        if (!Number.isFinite(budgetMs) || budgetMs < 1 || budgetMs > 3000) {
            return Promise.reject(new ScienceError('invalid_input', 'Unsupported compute budget.'));
        }
        let prepared;
        try { prepared = prepareScienceInput(solver, inputs); } catch (error) { return Promise.reject(error); }
        const id = `science-${++this.sequence}`;
        const deadline = this.now() + budgetMs;
        return new Promise((resolve, reject) => {
            let worker;
            try { worker = this.workerFactory(); } catch {
                reject(new ScienceError('unavailable', 'Browser compute is unavailable.'));
                return;
            }
            let timer;
            let finished = false;
            const finish = (error, result) => {
                if (finished) return;
                finished = true;
                this.clearTimer(timer);
                signal?.removeEventListener('abort', cancel);
                worker.removeEventListener('message', message);
                worker.removeEventListener('error', failed);
                worker.removeEventListener('messageerror', failed);
                worker.terminate();
                this.active = null;
                if (error) reject(error); else resolve(result);
            };
            const cancel = () => finish(new ScienceError('cancelled', 'Scientific computation cancelled.'));
            const failed = () => finish(new ScienceError('execution_failed', 'Browser computation failed.'));
            const message = ({ data }) => {
                if (data?.id !== id) return;
                // Timers may be throttled in background tabs; late results still expire.
                if (this.now() >= deadline) { finish(new ScienceError('timeout', 'Compute deadline exceeded.')); return; }
                if (data.kernel_version !== SCIENCE_KERNEL_VERSION) {
                    finish(new ScienceError('version_mismatch', 'Science runner version mismatch.')); return;
                }
                if (data.error) {
                    const allowed = ['invalid_input', 'version_mismatch', 'timeout', 'execution_failed'];
                    finish(new ScienceError(allowed.includes(data.error.code) ? data.error.code : 'execution_failed', 'Scientific computation failed.'));
                    return;
                }
                if (!data.result || data.result.kernel_version !== SCIENCE_KERNEL_VERSION) { failed(); return; }
                finish(null, data.result);
            };
            this.active = { cancel };
            worker.addEventListener('message', message);
            worker.addEventListener('error', failed);
            worker.addEventListener('messageerror', failed);
            signal?.addEventListener('abort', cancel, { once: true });
            if (signal?.aborted) { cancel(); return; }
            timer = this.setTimer(() => finish(new ScienceError('timeout', 'Compute deadline exceeded.')), budgetMs);
            try {
                worker.postMessage({ id, schema_version: SCIENCE_SCHEMA_VERSION, kernel_version: SCIENCE_KERNEL_VERSION,
                    solver, inputs: prepared, budget_ms: budgetMs });
            } catch { finish(new ScienceError('invalid_input', 'Scientific input cannot be transferred.')); }
        });
    }

    cancel() { this.active?.cancel(); }

    dispose() {
        this.disposed = true;
        this.cancel();
    }
}
