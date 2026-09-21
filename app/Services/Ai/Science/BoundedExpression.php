<?php

namespace App\Services\Ai\Science;

use Closure;
use Illuminate\Validation\ValidationException;

/** Compiles a finite arithmetic AST, never source code or arbitrary function names. */
final class BoundedExpression
{
    public function compile(array $expression, array $variables = ['x']): Closure
    {
        $nodes = 0;

        return $this->node($expression, $variables, 0, $nodes);
    }

    private function node(array $expression, array $variables, int $depth, int &$nodes): Closure
    {
        if ($depth > 12 || ++$nodes > 128) {
            $this->invalid('Expression resource limit exceeded.');
        }
        $op = $expression['op'] ?? null;
        if ($op === 'const') {
            if (array_key_exists('dimension', $expression)) {
                ScienceNumbers::dimension($expression['dimension']);
            }
            $value = $expression['value'] ?? null;
            if (! is_int($value) && ! is_float($value)) {
                $this->invalid('Constants must be finite numbers.');
            }
            $value = $this->finite((float) $value);

            return static fn (array $values): float => $value;
        }
        if ($op === 'var') {
            $name = $expression['name'] ?? null;
            if (! in_array($name, $variables, true)) {
                $this->invalid('Unknown expression variable.');
            }

            return fn (array $values): float => ScienceNumbers::finite($values[$name] ?? $this->invalid('Missing variable value.'));
        }
        $binary = ['add', 'sub', 'mul', 'div', 'pow'];
        $unary = ['neg', 'sin', 'cos', 'exp', 'log', 'sqrt'];
        $arity = in_array($op, $binary, true) ? 2 : (in_array($op, $unary, true) ? 1 : 0);
        $args = $expression['args'] ?? null;
        if ($arity === 0 || ! is_array($args) || ! array_is_list($args) || count($args) !== $arity) {
            $this->invalid('Unknown operator or wrong argument count.');
        }
        $children = [];
        foreach ($args as $arg) {
            if (! is_array($arg)) {
                $this->invalid('Expression arguments must be AST objects.');
            }
            $children[] = $this->node($arg, $variables, $depth + 1, $nodes);
        }

        return function (array $values) use ($op, $children): float {
            $a = $children[0]($values);
            $b = isset($children[1]) ? $children[1]($values) : 0.0;
            $value = match ($op) {
                'add' => $a + $b, 'sub' => $a - $b, 'mul' => $a * $b,
                'div' => $b == 0 ? $this->invalid('Division by zero.') : $a / $b,
                'pow' => $a ** $b, 'neg' => -$a, 'sin' => sin($a), 'cos' => cos($a),
                'exp' => exp($a), 'log' => $a <= 0 ? $this->invalid('Logarithm domain error.') : log($a),
                'sqrt' => $a < 0 ? $this->invalid('Square-root domain error.') : sqrt($a),
            };

            return $this->finite($value);
        };
    }

    private function finite(float $value): float
    {
        if (! is_finite($value)) {
            $this->invalid('Expression overflow or non-real result.');
        }

        return $value;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['expression' => $message]);
    }
}
