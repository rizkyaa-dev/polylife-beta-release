<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AiWriteAction
{
    public function toolName(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validatePayload(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $user, array $payload): Model;
}
