export const SCIENCE_SCHEMA_VERSION = 1;
export const SCIENCE_KERNEL_VERSION = 'science-kernel-1.8';

export class ScienceError extends Error {
    constructor(code, message) {
        super(message);
        this.name = 'ScienceError';
        this.code = code;
    }
}

export function invalid(message) {
    throw new ScienceError('invalid_input', message);
}

export function finite(value) {
    if (typeof value !== 'number' || !Number.isFinite(value)) invalid('Expected a finite number.');
    return value;
}

export function list(value, min, max = min) {
    if (!Array.isArray(value) || value.length < min || value.length > max) invalid('Invalid array dimension.');
    for (let i = 0; i < value.length; i++) {
        if (!Object.hasOwn(value, i)) invalid('Sparse arrays are unsupported.');
    }
    return value;
}

export function object(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) invalid('Expected an object.');
    return value;
}

export function tolerance(value, fallback, minimum) {
    const result = value === undefined ? fallback : finite(value);
    if (result < minimum || result > 1e-2) invalid('Tolerance is outside the supported range.');
    return result;
}
