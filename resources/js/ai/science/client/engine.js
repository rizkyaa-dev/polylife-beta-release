import { newQuickJSWASMModuleFromVariant } from 'quickjs-emscripten-core';
import variant from '@jitl/quickjs-singlefile-browser-release-sync';
import { CLIENT_LIMITS, validateProgram, validateResult } from './contract.js';

let modulePromise;

/** Initialize independently so startup failures cannot be mistaken for guest failures. */
export async function prepareClientEngine() {
    modulePromise ??= newQuickJSWASMModuleFromVariant(variant);
    try { return await modulePromise; }
    catch (error) { modulePromise = undefined; throw error; }
}

/** Guest code gets no host functions, module loader, DOM, network or storage bindings. */
export async function executeClientProgram(program, { computeMs = CLIENT_LIMITS.computeMs } = {}) {
    validateProgram(program);
    const engine = await prepareClientEngine();
    const runtime = engine.newRuntime();
    runtime.setMemoryLimit(CLIENT_LIMITS.heapBytes);
    runtime.setMaxStackSize(CLIENT_LIMITS.stackBytes);
    const deadline = performance.now() + Math.min(CLIENT_LIMITS.computeMs, Math.max(1, computeMs));
    runtime.setInterruptHandler(() => performance.now() >= deadline);
    const vm = runtime.newContext();
    try {
        const evaluate = source => {
            const result = vm.evalCode(source, 'client-computation.js');
            if (result.error) {
                let syntaxError = false;
                try { syntaxError = vm.dump(result.error)?.name === 'SyntaxError'; }
                finally { result.error.dispose(); }
                throw Object.assign(new Error('Client program failed or exceeded its resource limit.'),
                    { code: performance.now() >= deadline ? 'timeout' : syntaxError ? 'syntax_error' : 'execution_failed' });
            }
            return result.value;
        };
        evaluate(`"use strict";\n${program.source}`).dispose();
        const input = JSON.stringify(JSON.stringify(program.inputs));
        const handle = evaluate(`JSON.stringify(compute(JSON.parse(${input})), function(key, value) {
            if (typeof value === 'number' && !Number.isFinite(value)) throw new Error('Nonfinite result');
            if (typeof value === 'function' || typeof value === 'undefined' || typeof value === 'symbol') throw new Error('Non-JSON result');
            return value;
        })`);
        try {
            const text = vm.dump(handle);
            if (typeof text !== 'string' || new TextEncoder().encode(text).length > CLIENT_LIMITS.outputBytes) throw new Error('Invalid or excessive result.');
            return validateResult(JSON.parse(text), program.output_names);
        } finally { handle.dispose(); }
    } finally { vm.dispose(); runtime.dispose(); }
}
