import { finite, invalid, list, object } from './contract.js';

const binary = new Set(['add', 'sub', 'mul', 'div', 'pow']);
const unary = new Set(['neg', 'sin', 'cos', 'exp', 'log', 'sqrt']);

/** Compile only the bounded arithmetic vocabulary; never evaluate source text. */
export function compileExpression(expression, variables = ['x']) {
    let nodes = 0;
    const compile = (input, depth) => {
        const node = object(input);
        if (depth > 12 || ++nodes > 128) invalid('Expression resource limit exceeded.');
        if (node.op === 'const') {
            const value = finite(node.value);
            return () => value;
        }
        if (node.op === 'var') {
            if (!variables.includes(node.name)) invalid('Unknown expression variable.');
            return values => finite(values[node.name]);
        }
        const arity = binary.has(node.op) ? 2 : unary.has(node.op) ? 1 : 0;
        if (!arity) invalid('Unknown expression operator.');
        const children = list(node.args, arity).map(child => compile(child, depth + 1));
        return values => {
            const a = children[0](values);
            const b = children[1]?.(values);
            let result;
            switch (node.op) {
                case 'add': result = a + b; break;
                case 'sub': result = a - b; break;
                case 'mul': result = a * b; break;
                case 'div':
                    if (b === 0) invalid('Division by zero.');
                    result = a / b; break;
                case 'pow': result = a ** b; break;
                case 'neg': result = -a; break;
                case 'sin': result = Math.sin(a); break;
                case 'cos': result = Math.cos(a); break;
                case 'exp': result = Math.exp(a); break;
                case 'log':
                    if (a <= 0) invalid('Logarithm domain error.');
                    result = Math.log(a); break;
                case 'sqrt':
                    if (a < 0) invalid('Square-root domain error.');
                    result = Math.sqrt(a); break;
            }
            return finite(result);
        };
    };
    return compile(expression, 0);
}
