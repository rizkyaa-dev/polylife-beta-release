<?php

namespace App\Services\Ai\Science\Client;

/** Called only inside owner-scoped chat responses/views; never exposes continuation tokens. */
final class ClientComputationPresenter
{
    public static function details(?array $payload): ?array
    {
        if (($payload['execution_mode'] ?? null) !== 'client_script' || ! is_array($payload['client_program'] ?? null)) {
            return null;
        }

        return ['source' => $payload['client_program']['source'], 'inputs' => $payload['client_program']['inputs'],
            'program_hash' => $payload['client_program']['hash'], 'result' => $payload['result'] ?? null,
            'verification' => 'client_reported_only'];
    }
}
