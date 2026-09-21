<?php

namespace App\Services\Ai\Science\Models;

use App\Services\Ai\Science\ScienceUnitConverter;
use Illuminate\Validation\ValidationException;

/** Bounded literal checks, not a natural-language semantic verifier. */
final class SourceQuantityBinder
{
    private const ALIASES = [
        'V' => ['V', 'volt', 'volts'], 'ohm' => ['ohm', 'ohms', 'Ω'],
        'kohm' => ['kohm', 'kiloohm', 'kiloohms', 'kΩ'],
        'F' => ['F', 'farad', 'farads'], 'uF' => ['uF', 'µF', 'μF', 'microfarad', 'microfarads', 'mikrofarad'],
        's' => ['s', 'second', 'seconds', 'detik'], 'ms' => ['ms', 'millisecond', 'milliseconds', 'milidetik'],
        'min' => ['min', 'minute', 'minutes', 'menit'], 'h' => ['h', 'hour', 'hours', 'jam'],
    ];

    public function __construct(private readonly ScienceUnitConverter $units) {}

    public function bind(array $raw, array $dimension, string $source, ?string $role = null): array
    {
        $quote = $raw['quote'] ?? null;
        $unit = $raw['unit'] ?? null;
        if (! is_string($quote) || trim($quote) === '' || mb_strlen($quote) > 400 || ! str_contains($source, $quote)
            || ! is_string($unit) || ! isset(self::ALIASES[$unit])) {
            $this->invalid('A supported unit and exact source quote are required.');
        }
        $quantity = $this->units->normalize($raw['value'] ?? null, $unit);
        if ($quantity['dimension'] !== $dimension) {
            $this->invalid('Quantity has an invalid physical unit for its role.');
        }
        $aliases = implode('|', array_map(fn ($alias) => preg_quote($alias, '/'), self::ALIASES[$unit]));
        $pattern = '/(?<![\p{L}\p{N}_.\/^*+\-−×÷±])([+\-−]?(?:\d+(?:[.,]\d*)?|[.,]\d+)(?:[eE][+-]?\d+)?)\s*(?:'.$aliases.')(?![\p{L}\p{N}_\/^*×÷])/u';
        preg_match_all($pattern, $quote, $matches);
        foreach ($matches[1] as $number) {
            $value = (float) str_replace([',', '−'], ['.', '-'], $number);
            $expected = $quantity['source_value'];
            if (is_finite($value) && ($value === $expected
                || abs($value - $expected) <= 1e-12 * max(abs($value), abs($expected)))) {
                return $quantity + ['binding' => 'number_unit_occurrence_checked', 'quote' => $quote];
            }
        }
        // These narrowly scoped interpretations remain explicit, never promoted
        // to proof that negation/context or the user's intended time origin is understood.
        if ($quantity['si_value'] === 0.0 && $role === 'initial_voltage'
            && preg_match('/uncharged|tidak bermuatan|tak bermuatan|tanpa muatan/iu', $quote)) {
            return $quantity + ['binding' => 'uncharged_initial_interpretation', 'quote' => $quote];
        }
        if ($quantity['si_value'] === 0.0 && $role === 'time_origin'
            && preg_match('/initial|awal/iu', $quote)) {
            return $quantity + ['binding' => 'zero_time_origin_assumed', 'quote' => $quote];
        }
        $this->invalid('The declared number and unit do not occur together in its source quote. Use the generic unverified path for unsupported notation, not fabricated evidence.');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['domain_model' => $message]);
    }
}
