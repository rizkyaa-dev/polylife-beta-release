/** The development entry is served by Laravel; guest programs never choose its URL. */
export function createClientWorker() {
    if (import.meta.env?.DEV && globalThis.location
        && new URL(import.meta.url).origin !== globalThis.location.origin) {
        return new Worker(new URL('/ai/science/client-worker.js', globalThis.location.origin),
            { type: 'module', name: 'polylife-client-computation' });
    }
    return new Worker(new URL('./worker.js', import.meta.url),
        { type: 'module', name: 'polylife-client-computation' });
}
