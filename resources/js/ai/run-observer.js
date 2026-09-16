import { getJson } from './transport.js';
import { terminalRunError } from './turn-recovery.js';

/** Observing a run can fail without failing or cancelling that run. */
export async function observeAiRun(url, {
    read = getJson,
    now = Date.now,
    sleep = ms => new Promise(resolve => globalThis.setTimeout(resolve, ms)),
    hidden = () => globalThis.document?.hidden ?? false,
    random = Math.random,
    onProgress = () => {},
    timeoutMs = 360_000,
} = {}) {
    const startedAt = now();
    let failures = 0;
    while (now() - startedAt < timeoutMs) {
        let data;
        try {
            data = await read(url);
            if (!['success', 'running', 'failed', 'cancelled'].includes(data?.status)) {
                throw new Error('Respons status proses tidak valid. Coba periksa kembali.');
            }
            failures = 0;
        } catch (error) {
            if (++failures >= 3) throw error;
        }
        if (data?.status === 'success') return data;
        if (data?.status === 'failed') throw terminalRunError(data.message || 'Proses AI gagal diselesaikan.');
        if (data?.status === 'cancelled') throw terminalRunError(data.message || 'Proses AI dihentikan.', true);
        if (data?.status === 'running') onProgress(data);
        const elapsed = now() - startedAt;
        const delay = hidden() ? 15_000 : elapsed < 5_000 ? 1_000 : elapsed < 20_000 ? 2_000 : elapsed < 60_000 ? 4_000 : 8_000;
        await sleep(Math.min(delay + Math.floor(delay * (random() * 0.2 - 0.1)), Math.max(0, timeoutMs - elapsed)));
    }
    throw new Error('Pemantauan terhenti setelah beberapa menit. Coba lagi untuk memeriksa proses yang sama.');
}
