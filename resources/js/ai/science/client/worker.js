let engine;
let started = false;

self.onmessage = async ({ data }) => {
    if (data?.type === 'initialize') {
        try {
            engine = await import('./engine.js');
            await engine.prepareClientEngine();
            self.postMessage({ ready: true });
        } catch { self.postMessage({ failure: 'unavailable' }); }
        return;
    }
    if (!engine || started) return;
    started = true;
    try {
        self.postMessage({ result: await engine.executeClientProgram(data.program) });
    } catch (error) {
        self.postMessage({ failure: ['timeout', 'syntax_error'].includes(error.code) ? error.code : 'execution_failed' });
    }
};
