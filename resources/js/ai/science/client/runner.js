import { CLIENT_LIMITS, validateProgram, validateResult } from './contract.js';
import { createClientWorker } from './worker-factory.js';

export class ClientComputationRunner {
    constructor() { this.active = null; }

    run(program, { signal } = {}) {
        validateProgram(program);
        if (this.active) throw Object.assign(new Error('Client runner is busy.'), { code: 'unavailable' });
        return new Promise((resolve, reject) => {
            if (signal?.aborted) { reject(Object.assign(new Error('Cancelled.'), { code: 'cancelled' })); return; }
            let worker;
            let started = false;
            let startupAttempts = 0;
            let settled = false;
            const finish = (failure, result) => {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                signal?.removeEventListener('abort', abort);
                worker?.terminate();
                if (worker) { worker.onmessage = null; worker.onerror = null; worker.onmessageerror = null; }
                this.active = null;
                failure ? reject(Object.assign(new Error('Client computation stopped.'), { code: failure })) : resolve(result);
            };
            const abort = () => finish('cancelled');
            const timer = setTimeout(() => finish('timeout'), CLIENT_LIMITS.wallMs);
            this.active = { abort };
            signal?.addEventListener('abort', abort, { once: true });
            const startupFailure = () => {
                if (settled) return;
                if (started) { finish('execution_failed'); return; }
                if (worker) { worker.terminate(); worker.onmessage = null; worker.onerror = null; }
                if (startupAttempts < 2) launch(); else finish('unavailable');
            };
            const receive = ({ data }) => {
                if (settled) return;
                if (!data || typeof data !== 'object') { finish('invalid_input'); return; }
                if (data.ready === true && !started) {
                    started = true;
                    try { worker.postMessage({ program }); } catch { finish('execution_failed'); }
                    return;
                }
                if (!started && data.failure === 'unavailable') { startupFailure(); return; }
                if (data.failure) {
                    finish(['timeout', 'syntax_error', 'execution_failed'].includes(data.failure) ? data.failure : 'invalid_input');
                    return;
                }
                if (!started) { finish('invalid_input'); return; }
                try { finish(null, validateResult(data.result, program.output_names)); } catch { finish('invalid_input'); }
            };
            const launch = () => {
                startupAttempts++;
                try { worker = createClientWorker(); }
                catch { startupFailure(); return; }
                if (settled) { worker.terminate(); return; }
                if (signal?.aborted) { finish('cancelled'); return; }
                const current = worker;
                worker.onerror = () => { if (worker === current) startupFailure(); };
                worker.onmessageerror = () => { if (worker === current) finish('invalid_input'); };
                worker.onmessage = event => { if (worker === current) receive(event); };
                try { worker.postMessage({ type: 'initialize' }); } catch { finish('execution_failed'); }
            };
            launch();
        });
    }

    dispose() { this.active?.abort(); }
}
