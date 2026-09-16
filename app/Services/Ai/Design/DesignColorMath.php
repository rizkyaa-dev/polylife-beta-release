<?php

namespace App\Services\Ai\Design;

use InvalidArgumentException;

/** sRGB relative luminance; opaque six-digit hex only, not composited CSS colors. */
final class DesignColorMath
{
    public function contrast(string $first, string $second): float
    {
        $a = $this->luminance($first);
        $b = $this->luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /** Preserve the seed hue approximately; adjust toward black/white if a role fails. */
    public function readable(string $seed, string $surface, float $minimum = 4.5): string
    {
        if ($minimum < 1 || $minimum > 21) {
            throw new InvalidArgumentException('Contrast threshold must be between 1 and 21.');
        }
        if ($this->contrast($seed, $surface) >= $minimum) {
            return strtolower($seed);
        }
        $target = $this->contrast('#000000', $surface) >= $this->contrast('#ffffff', $surface) ? '#000000' : '#ffffff';
        if ($this->contrast($target, $surface) < $minimum) {
            throw new InvalidArgumentException('Requested contrast is not achievable on this surface.');
        }
        for ($step = 1; $step <= 100; $step++) {
            $candidate = $this->mix($seed, $target, $step / 100);
            if ($this->contrast($candidate, $surface) >= $minimum) {
                return $candidate;
            }
        }

        return $target;
    }

    public function foreground(string $surface): string
    {
        return $this->contrast('#000000', $surface) >= $this->contrast('#ffffff', $surface) ? '#000000' : '#ffffff';
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(function (int $channel): float {
            $srgb = $channel / 255;

            return $srgb <= 0.04045 ? $srgb / 12.92 : (($srgb + 0.055) / 1.055) ** 2.4;
        }, $this->channels($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function mix(string $from, string $to, float $amount): string
    {
        $a = $this->channels($from);
        $b = $this->channels($to);

        return sprintf('#%02x%02x%02x', ...array_map(fn (int $index): int => (int) round($a[$index] + ($b[$index] - $a[$index]) * $amount), [0, 1, 2]));
    }

    /** @return list<int> */
    private function channels(string $hex): array
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/D', $hex) !== 1) {
            throw new InvalidArgumentException('Expected an opaque six-digit hex color.');
        }

        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }
}
