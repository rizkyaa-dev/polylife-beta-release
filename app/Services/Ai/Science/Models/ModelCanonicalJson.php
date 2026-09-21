<?php

namespace App\Services\Ai\Science\Models;

use Illuminate\Validation\ValidationException;

/** Stable bounded model identities across object-key order and JSON integer/float roundtrips. */
final class ModelCanonicalJson
{
    public static function encode(array $value): string
    {
        $nodes = 0;
        $normalize = function (mixed $item, int $depth = 0) use (&$normalize, &$nodes): mixed {
            if ($depth > 32 || ++$nodes > 8192 || (is_float($item) && ! is_finite($item))) {
                throw ValidationException::withMessages(['domain_model' => 'Canonical model resource or numeric limit exceeded.']);
            }
            if (! is_array($item)) {
                if (is_object($item) || is_resource($item)) {
                    throw ValidationException::withMessages(['domain_model' => 'Only JSON model values are allowed.']);
                }

                return $item;
            }
            if (! array_is_list($item)) {
                ksort($item, SORT_STRING);
            }
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child, $depth + 1);
            }

            return $item;
        };

        return json_encode($normalize($value), JSON_THROW_ON_ERROR);
    }
}
