import { executeClientProgram } from '../../resources/js/ai/science/client/engine.js';

// Exercises the actual browser guest engine in Node, not a browser Worker or UI.
let input = '';
for await (const chunk of process.stdin) input += chunk;
try {
    const result = await executeClientProgram(JSON.parse(input));
    process.stdout.write(JSON.stringify({ result }));
} catch (error) {
    process.stdout.write(JSON.stringify({ failure: ['timeout', 'syntax_error'].includes(error.code) ? error.code : 'execution_failed' }));
}
