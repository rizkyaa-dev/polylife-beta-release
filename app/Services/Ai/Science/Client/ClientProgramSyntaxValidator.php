<?php

namespace App\Services\Ai\Science\Client;

use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/** Parse only: submitted JavaScript is never evaluated by the server. */
final class ClientProgramSyntaxValidator
{
    public function validate(string $source, ?float $deadlineAt = null): void
    {
        $timeout = $deadlineAt === null ? 2.0 : min(2.0, $deadlineAt - hrtime(true) / 1_000_000_000);
        if ($timeout <= 0) {
            throw AiProviderException::timeout();
        }
        $process = new Process([
            (string) config('services.ai_science_syntax_node_binary', 'node'),
            '--max-old-space-size=32', '--check', '--input-type=commonjs',
        ], env: ['NODE_OPTIONS' => false, 'NODE_PATH' => false], timeout: $timeout);
        $process->setInput("\"use strict\";\n".$source);
        try {
            $process->run();
        } catch (ProcessTimedOutException $error) {
            if ($deadlineAt !== null && hrtime(true) / 1_000_000_000 >= $deadlineAt) {
                throw AiProviderException::timeout();
            }
            throw new RuntimeException('Client script syntax checker timed out.', previous: $error);
        }
        if ($process->isSuccessful()) {
            return;
        }
        if (str_contains($process->getErrorOutput(), 'SyntaxError:')) {
            throw ValidationException::withMessages([
                'program.source' => 'Invalid JavaScript syntax. Return a complete corrected program; quote property keys containing punctuation, decimals, spaces or units. Preserve inputs and requested outputs.',
            ]);
        }
        // Infrastructure failures are not model errors and must not consume a repair call.
        throw new RuntimeException('Client script syntax checker is unavailable.');
    }
}
