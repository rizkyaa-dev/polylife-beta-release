<?php

namespace App\Services\Ai\Design;

use App\Services\Ai\DTOs\AiDesignIntent;

final class AiDesignTokenCompiler
{
    public function __construct(private readonly DesignColorMath $colors) {}

    /** @return array<string, mixed> */
    public function compile(AiDesignIntent $intent): array
    {
        $dark = $intent->value('color_mode') === 'dark';
        $family = $intent->value('color_family');
        $surfaces = match ($family) {
            'warm' => $dark ? ['#211e1a', '#2d2924'] : ['#faf7f2', '#ffffff'],
            'cool' => $dark ? ['#171e26', '#222c36'] : ['#f5f8fb', '#ffffff'],
            default => $dark ? ['#1b1b1b', '#282828'] : ['#fafafa', '#ffffff'],
        };
        $seed = $intent->value('accent_hex') ?? match ($family) {
            'warm' => '#9a4e28', 'cool' => '#245c99', default => '#505050',
        };
        $text = $dark ? '#f5f5f5' : '#202020';
        $muted = $dark ? '#b5b5b5' : '#656565';
        // Validate shared foregrounds against every surface they are recommended for.
        foreach ($surfaces as $surface) {
            $text = $this->colors->readable($text, $surface);
            $muted = $this->colors->readable($muted, $surface);
        }
        $link = $seed;
        foreach ($surfaces as $surface) {
            $link = $this->colors->readable($link, $surface);
        }
        $border = $dark ? '#939393' : '#808080';
        foreach ($surfaces as $surface) {
            $border = $this->colors->readable($border, $surface, 3);
        }
        $pairs = ['text/surface' => [$text, $surfaces[0]], 'text/raised' => [$text, $surfaces[1]],
            'muted/surface' => [$muted, $surfaces[0]], 'muted/raised' => [$muted, $surfaces[1]],
            'link/surface' => [$link, $surfaces[0]], 'link/raised' => [$link, $surfaces[1]],
            'on-accent/accent' => [$this->colors->foreground($seed), $seed]];
        $contrast = [];
        foreach ($pairs as $name => [$foreground, $background]) {
            $ratio = $this->colors->contrast($foreground, $background);
            $contrast[$name] = ['ratio' => round($ratio, 3), 'minimum' => 4.5, 'pass' => $ratio >= 4.5];
        }
        foreach ($surfaces as $index => $surface) {
            $ratio = $this->colors->contrast($border, $surface);
            $contrast['control-border/'.($index === 0 ? 'surface' : 'raised')] = ['ratio' => round($ratio, 3), 'minimum' => 3, 'pass' => $ratio >= 3];
        }
        $reading = $intent->value('goal') === 'reading';
        $compact = $intent->value('density') === 'compact';
        $body = $reading ? 18 : 16;
        $ratio = $compact ? 1.2 : 1.25;
        [$outer, $inset] = match ($intent->value('expression')) {
            'restrained' => [8, 4], 'expressive' => [20, 8], default => [12, 4],
        };

        return [
            'units' => 'CSS px for web; adapt logical units for native UI',
            'palette_selected' => $family !== 'unknown' || $intent->value('accent_hex') !== null,
            'palette_selection' => $family === 'unknown' && $intent->value('accent_hex') === null
                ? 'unselected; safe reference palette only, choose a coherent creative palette and verify its actual contrast'
                : 'planner-selected family/accent; preserve explicit user direction',
            'colors' => ['surface' => $surfaces[0], 'raised' => $surfaces[1], 'text' => $text, 'muted' => $muted,
                'accent' => $seed, 'on_accent' => $this->colors->foreground($seed), 'link' => $link,
                'focus' => $link, 'control_border' => $border],
            'spacing' => ['unit' => 4, 'scale' => [4, 8, 12, 16, 24, 32, 48, 64, 96],
                'item_gap' => $compact ? 8 : 12, 'group_gap' => $compact ? 24 : 32,
                'section_gap' => $reading ? 48 : ($compact ? 32 : 64)],
            'type' => ['body' => $body, 'ratio' => $ratio, 'scale' => array_map(fn (int $n): float => round($body * $ratio ** $n, 2), [0, 1, 2, 3, 4]),
                'body_line_height' => 1.5, 'measure_ch' => $reading ? 65 : 60],
            'controls' => ['minimum_hit_area' => 24, 'recommended_height' => $intent->value('interaction') === 'pointer' ? 40 : 44,
                'padding_inline' => 16, 'icon' => 20],
            'geometry' => ['outer_radius' => $outer, 'inset' => $inset, 'inner_radius' => $this->innerRadius($outer, $inset)],
            'verification' => ['scope' => 'opaque token pairs only, not rendered UI', 'contrast' => $contrast],
        ];
    }

    public function innerRadius(float $outer, float $inset): float
    {
        if (! is_finite($outer) || ! is_finite($inset) || $outer < 0 || $inset < 0) {
            throw new \InvalidArgumentException('Radius and inset must be finite and non-negative.');
        }

        return max(0, $outer - $inset);
    }
}
