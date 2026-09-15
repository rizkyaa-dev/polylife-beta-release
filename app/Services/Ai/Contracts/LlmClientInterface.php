<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;

interface LlmClientInterface
{
    /**
     * @param  list<LlmMessage>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    public function chat(
        array $messages,
        array $tools = [],
        ?string $systemInstruction = null,
        ?LlmRequestOptions $options = null
    ): LlmResponse;
}
