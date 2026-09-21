<?php

namespace App\Services\Ai\Science\Models;

/** Immutable, server-generated preparation; never deserialize this from a client result. */
final readonly class PreparedScienceModel
{
    public function __construct(
        public string $solver,
        public array $inputs,
        public array $checks,
        public array $stateOrder,
    ) {}
}
