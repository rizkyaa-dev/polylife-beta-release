<?php

namespace App\Services\Ai\Contracts;

use App\Models\User;

interface AiToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed> JSON Schema of parameters
     */
    public function schema(): array;

    /**
     * Whether this tool modifies data and requires user confirmation.
     */
    public function isMutating(): bool;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(User $user, array $arguments): array;
}
