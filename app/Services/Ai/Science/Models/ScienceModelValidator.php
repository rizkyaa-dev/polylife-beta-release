<?php

namespace App\Services\Ai\Science\Models;

interface ScienceModelValidator
{
    public function domain(): string;

    public function specification(): array;

    public function prepare(array $model, string $originalProblem): PreparedScienceModel;
}
