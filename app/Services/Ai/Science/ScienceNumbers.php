<?php

namespace App\Services\Ai\Science;

use Illuminate\Validation\ValidationException;

/** Identical JSON number/list semantics in the PHP and browser kernels. */
final class ScienceNumbers
{
    public static function finite(mixed $value): float
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            throw ValidationException::withMessages(['inputs' => 'Expected a finite JSON number, without coercion.']);
        }

        return (float) $value;
    }

    public static function list(array $values): void
    {
        if (! array_is_list($values)) {
            throw ValidationException::withMessages(['inputs' => 'Vectors and matrices must be JSON lists.']);
        }
    }

    public static function dimension(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) !== 7) {
            throw ValidationException::withMessages(['dimensions' => 'SI dimensions must be seven-number exponent vectors.']);
        }

        return array_map(function ($exponent): float {
            $number = self::finite($exponent);
            if (abs($number) > 32) {
                throw ValidationException::withMessages(['dimensions' => 'Dimension exponents must be finite and bounded.']);
            }

            return $number;
        }, $value);
    }
}
