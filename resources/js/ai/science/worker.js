import { SCIENCE_KERNEL_VERSION, SCIENCE_SCHEMA_VERSION, ScienceError } from './contract.js';
import { solveScience } from './solvers.js';

// Trusted module only: messages contain solver data, never URLs or source code.
self.addEventListener('message', ({ data }) => {
    const id = data?.id;
    if (typeof id !== 'string' || id.length > 80) return;
    try {
        if (data.schema_version !== SCIENCE_SCHEMA_VERSION || data.kernel_version !== SCIENCE_KERNEL_VERSION) {
            throw new ScienceError('version_mismatch', 'Science runner version mismatch.');
        }
        const budget = data.budget_ms;
        if (!Number.isFinite(budget) || budget < 1 || budget > 3000) {
            throw new ScienceError('invalid_input', 'Unsupported compute budget.');
        }
        const deadline = performance.now() + budget;
        const result = solveScience(data.solver, data.inputs, () => {
            if (performance.now() >= deadline) throw new ScienceError('timeout', 'Compute deadline exceeded.');
        });
        self.postMessage({ id, kernel_version: SCIENCE_KERNEL_VERSION, result });
    } catch (error) {
        self.postMessage({ id, kernel_version: SCIENCE_KERNEL_VERSION, error: {
            code: error instanceof ScienceError ? error.code : 'execution_failed',
            message: error instanceof ScienceError ? error.message : 'Scientific computation failed.',
        } });
    }
});
