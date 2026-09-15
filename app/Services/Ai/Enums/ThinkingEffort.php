<?php

namespace App\Services\Ai\Enums;

enum ThinkingEffort: string
{
    case Off = 'off';
    case Low = 'low';
    case High = 'high';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Low => 'Low',
            self::High => 'High',
            self::Max => 'Max',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Off => 'Respons paling cepat untuk percakapan ringan.',
            self::Low => 'Penalaran singkat untuk tugas sederhana.',
            self::High => 'Seimbang untuk sebagian besar tugas agentic.',
            self::Max => 'Penalaran terdalam dengan waktu respons lebih lama.',
        };
    }

    public function isEnabled(): bool
    {
        return $this !== self::Off;
    }

    public function deepSeekValue(): string
    {
        return $this === self::Off ? 'none' : $this->value;
    }
}
