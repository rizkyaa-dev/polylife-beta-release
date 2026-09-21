<?php

namespace App\Services\Ai\Science;

use Illuminate\Validation\ValidationException;

/** Trusted finite unit registry plus path-bound conversion provenance. */
final class ScienceUnitConverter
{
    private const ZERO = [0, 0, 0, 0, 0, 0, 0];

    private const UNITS = [
        'm' => [1, 0, [0, 1, 0, 0, 0, 0, 0], 'm'], 'cm' => [0.01, 0, [0, 1, 0, 0, 0, 0, 0], 'm'],
        'mm' => [0.001, 0, [0, 1, 0, 0, 0, 0, 0], 'm'], 'km' => [1000, 0, [0, 1, 0, 0, 0, 0, 0], 'm'],
        's' => [1, 0, [0, 0, 1, 0, 0, 0, 0], 's'], 'ms' => [0.001, 0, [0, 0, 1, 0, 0, 0, 0], 's'],
        'min' => [60, 0, [0, 0, 1, 0, 0, 0, 0], 's'], 'h' => [3600, 0, [0, 0, 1, 0, 0, 0, 0], 's'],
        'kg' => [1, 0, [1, 0, 0, 0, 0, 0, 0], 'kg'], 'g' => [0.001, 0, [1, 0, 0, 0, 0, 0, 0], 'kg'],
        'K' => [1, 0, [0, 0, 0, 0, 1, 0, 0], 'K'], 'degC' => [1, 273.15, [0, 0, 0, 0, 1, 0, 0], 'K'],
        'A' => [1, 0, [0, 0, 0, 1, 0, 0, 0], 'A'], 'mA' => [0.001, 0, [0, 0, 0, 1, 0, 0, 0], 'A'],
        'N' => [1, 0, [1, 1, -2, 0, 0, 0, 0], 'N'], 'J' => [1, 0, [1, 2, -2, 0, 0, 0, 0], 'J'],
        'W' => [1, 0, [1, 2, -3, 0, 0, 0, 0], 'W'], 'Pa' => [1, 0, [1, -1, -2, 0, 0, 0, 0], 'Pa'],
        'V' => [1, 0, [1, 2, -3, -1, 0, 0, 0], 'V'],
        'ohm' => [1, 0, [1, 2, -3, -2, 0, 0, 0], 'ohm'], 'kohm' => [1000, 0, [1, 2, -3, -2, 0, 0, 0], 'ohm'],
        'F' => [1, 0, [-1, -2, 4, 2, 0, 0, 0], 'F'], 'uF' => [1e-6, 0, [-1, -2, 4, 2, 0, 0, 0], 'F'],
        'rad/s' => [1, 0, [0, 0, -1, 0, 0, 0, 0], 'rad/s'],
    ];

    /** Normalize structured domain quantities using the same registry as kernel provenance. */
    public function normalize(mixed $value, string $unit): array
    {
        $source = ScienceNumbers::finite($value);
        $definition = self::UNITS[$unit] ?? $this->invalid('Source unit is unsupported.');
        $si = ScienceNumbers::finite($source * $definition[0] + $definition[1]);
        if ($source !== 0.0 && $definition[1] === 0 && $si === 0.0) {
            $this->invalid('Source conversion underflows the supported numeric precision.');
        }

        return ['source_value' => $source, 'source_unit' => $unit, 'si_value' => $si, 'dimension' => $definition[2]];
    }

    public function verify(string $solver, array $inputs): array
    {
        if (! array_key_exists('conversions', $inputs)) {
            return ['status' => 'unverified', 'reason' => 'No structured source-unit conversions supplied.'];
        }
        $items = $inputs['conversions'];
        if (! is_array($items) || ! array_is_list($items) || count($items) > 32) {
            $this->invalid('Conversions must be a list of at most 32 items.');
        }
        $verified = [];
        foreach ($items as $index => $item) {
            if (! is_array($item) || ! is_array($item['path'] ?? null) || ! array_is_list($item['path'])
                || count($item['path']) < 1 || count($item['path']) > 32 || ! is_string($item['source_unit'] ?? null)) {
                $this->invalid('Malformed conversion provenance.');
            }
            $source = ScienceNumbers::finite($item['source_value'] ?? null);
            $definition = self::UNITS[$item['source_unit']] ?? $this->invalid('Source unit is unsupported.');
            try {
                [$target, $dimension] = $this->target($solver, $inputs, $item['path']);
            } catch (ValidationException $exception) {
                $path = mb_substr(json_encode($item['path'], JSON_THROW_ON_ERROR), 0, 500);
                $this->invalid('Conversion entry '.$index.' at '.$path.': '.$exception->getMessage());
            }
            $expected = $this->normalize($source, $item['source_unit'])['si_value'];
            // No unit-sized absolute floor: that would accept zero for tiny SI quantities.
            if ($target !== $expected && abs($target - $expected) > 1e-12 * max(abs($target), abs($expected))) {
                $this->invalid('Converted value does not match the solver input.');
            }
            $actualDimension = ScienceNumbers::dimension($dimension);
            $expectedDimension = ScienceNumbers::dimension($definition[2]);
            foreach ($actualDimension as $index => $exponent) {
                if (abs($exponent - $expectedDimension[$index]) > 1e-9) {
                    $this->invalid('Source unit does not match the solver input dimension.');
                }
            }
            $verified[] = ['path' => $item['path'], 'source_value' => $source, 'source_unit' => $item['source_unit'],
                'si_value' => $expected, 'si_unit' => $definition[3]];
        }

        return $items === []
            ? ['status' => 'none_declared', 'limitations' => 'An empty list does not prove that the original problem used SI units.']
            : ['status' => 'declared_conversions_checked', 'items' => $verified,
                'limitations' => 'Checks declared source values against bound SI inputs; it cannot prove omitted conversions or measurement validity.'];
    }

    private function target(string $solver, array $inputs, array $path): array
    {
        $dimensions = $inputs['dimensions'] ?? $this->invalid('Conversions require structured dimensions.');
        if (! is_array($dimensions)) {
            $this->invalid('Conversions require structured dimensions.');
        }
        if ($solver === 'linear_system' && count($path) === 3 && $path[0] === 'matrix' && is_int($path[1]) && is_int($path[2])) {
            return [$this->numberAt($inputs, $path), $dimensions['matrix'][$path[1]][$path[2]] ?? null];
        }
        if ($solver === 'linear_system' && count($path) === 2 && $path[0] === 'rhs' && is_int($path[1])) {
            return [$this->numberAt($inputs, $path), $dimensions['rhs'][$path[1]] ?? null];
        }
        if (in_array($solver, ['integrate', 'root_scalar'], true) && count($path) === 1 && in_array($path[0], ['lower', 'upper'], true)) {
            return [$this->numberAt($inputs, $path), $dimensions['x'] ?? null];
        }
        if ($solver === 'ode_ivp' && count($path) === 1 && in_array($path[0], ['t_start', 't_end'], true)) {
            return [$this->numberAt($inputs, $path), $dimensions['t'] ?? null];
        }
        if ($solver === 'ode_ivp' && count($path) === 2 && $path[0] === 'initial' && is_int($path[1])) {
            return [$this->numberAt($inputs, $path), $dimensions['states'][$path[1]] ?? null];
        }
        if (in_array($solver, ['integrate', 'root_scalar'], true) && $path[0] === 'expression') {
            return $this->astTarget($inputs['expression'] ?? [], array_slice($path, 1));
        }
        if ($solver === 'ode_ivp' && ($path[0] ?? null) === 'derivatives' && is_int($path[1] ?? null)) {
            return $this->astTarget($inputs['derivatives'][$path[1]] ?? [], array_slice($path, 2));
        }
        if (($path[0] ?? null) === 'outputs' && is_int($path[1] ?? null) && ($path[2] ?? null) === 'expression') {
            return $this->astTarget($inputs['outputs'][$path[1]]['expression'] ?? [], array_slice($path, 3));
        }
        $this->invalid('Conversion path is not a supported solver numeric field.');
    }

    private function astTarget(mixed $node, array $path): array
    {
        if (! is_array($node)) {
            $this->invalid('Conversion path does not resolve to an expression node.');
        }
        if ($path === ['value'] && ($node['op'] ?? null) === 'const') {
            return [ScienceNumbers::finite($node['value'] ?? null), $node['dimension'] ?? self::ZERO];
        }
        if (count($path) >= 2 && $path[0] === 'args' && is_int($path[1])) {
            return $this->astTarget($node['args'][$path[1]] ?? null, array_slice($path, 2));
        }
        $this->invalid('Conversion path must end at a bounded constant value.');
    }

    private function numberAt(array $inputs, array $path): float
    {
        $value = $inputs;
        foreach ($path as $segment) {
            if ((! is_string($segment) && ! is_int($segment)) || ! is_array($value) || ! array_key_exists($segment, $value)) {
                $this->invalid('Conversion path does not resolve to a solver value.');
            }
            $value = $value[$segment];
        }

        return ScienceNumbers::finite($value);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['conversions' => $message]);
    }
}
