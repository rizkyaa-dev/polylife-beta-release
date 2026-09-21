<?php

namespace App\Services\Ai\Science;

interface ScienceSolver
{
    public function name(): string;

    /** JSON schema and operational limits presented to the planner. */
    public function specification(): array;

    /** Pure, bounded computation; no model, network or workspace access. */
    public function solve(array $inputs): array;
}
