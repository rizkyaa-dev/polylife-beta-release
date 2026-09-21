export const CLIENT_JS_VERSION = 'client-js-1';
export const CLIENT_LIMITS = Object.freeze({ sourceBytes: 12000, inputBytes: 8192, outputBytes: 16384, heapBytes: 16 * 1024 * 1024, stackBytes: 512 * 1024, computeMs: 5000, wallMs: 15000 });

export function validateJson(value, byteLimit) {
    let nodes = 0;
    const walk = (item, depth) => {
        if (++nodes > 2048 || depth > 12) throw new Error('JSON resource limit exceeded.');
        if (typeof item === 'number' && !Number.isFinite(item)) throw new Error('Nonfinite value.');
        if (item !== null && typeof item === 'object') {
            if (!Array.isArray(item) && Object.getPrototypeOf(item) !== Object.prototype) throw new Error('Only plain JSON is supported.');
            for (const child of Object.values(item)) walk(child, depth + 1);
        } else if (!['string', 'number', 'boolean'].includes(typeof item) && item !== null) throw new Error('Invalid JSON value.');
    };
    walk(value, 0);
    const text = JSON.stringify(value);
    if (new TextEncoder().encode(text).length > byteLimit) throw new Error('JSON byte limit exceeded.');
    return text;
}

export function validateProgram(program) {
    if (program?.version !== CLIENT_JS_VERSION || typeof program.source !== 'string' || !program.source.trim()
        || new TextEncoder().encode(program.source).length > CLIENT_LIMITS.sourceBytes
        || !program.inputs || typeof program.inputs !== 'object' || !/^[a-f0-9]{64}$/.test(program.hash ?? '')
        || !Array.isArray(program.checks) || program.checks.length < 1 || program.checks.length > 8
        || program.checks.some(check => typeof check !== 'string' || !check.trim() || check.length > 200)) throw new Error('Invalid client program.');
    validateJson(program.inputs, CLIENT_LIMITS.inputBytes);
    if (program.output_names !== undefined && (!Array.isArray(program.output_names) || program.output_names.length < 1
        || program.output_names.length > 32 || new Set(program.output_names).size !== program.output_names.length
        || program.output_names.some(name => typeof name !== 'string' || !name || name.length > 128))) throw new Error('Invalid output contract.');
}

export function validateResult(result, outputNames) {
    validateJson(result, CLIENT_LIMITS.outputBytes);
    if (!result?.values || Array.isArray(result.values) || typeof result.values !== 'object'
        || Object.keys(result.values).length < 1 || Object.keys(result.values).length > 32
        || !Array.isArray(result.checks) || result.checks.length > 8) throw new Error('Invalid result envelope.');
    if (outputNames && JSON.stringify(Object.keys(result.values).sort()) !== JSON.stringify([...outputNames].sort())) throw new Error('Output contract mismatch.');
    for (const check of result.checks) {
        if (!check || typeof check.name !== 'string' || check.name.length > 200 || typeof check.passed !== 'boolean'
            || (check.residual !== undefined && !Number.isFinite(check.residual))) throw new Error('Invalid check report.');
    }
    // Strip any script-supplied verification, runner, or authority claims.
    return { values: result.values, checks: result.checks.map(({ name, passed, residual }) => ({ name, passed, ...(residual === undefined ? {} : { residual }) })) };
}
