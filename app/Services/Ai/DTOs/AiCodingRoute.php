<?php

namespace App\Services\Ai\DTOs;

final class AiCodingRoute
{
    public function __construct(
        public readonly string $language,
        public readonly string $instructions
    ) {}
}
