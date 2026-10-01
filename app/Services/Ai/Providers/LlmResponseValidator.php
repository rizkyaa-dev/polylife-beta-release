<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Exceptions\AiProviderException;

final class LlmResponseValidator
{
    public static function object(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw AiProviderException::invalidResponse();
        }

        return $value;
    }

    public static function optionalText(mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw AiProviderException::invalidResponse();
        }

        return $value;
    }

    public static function list(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw AiProviderException::invalidResponse();
        }

        return $value;
    }

    public static function usable(LlmResponse $response): LlmResponse
    {
        if (! $response->isTruncated() && ! $response->hasToolCalls() && blank($response->content)) {
            throw AiProviderException::invalidResponse();
        }

        return $response;
    }
}
