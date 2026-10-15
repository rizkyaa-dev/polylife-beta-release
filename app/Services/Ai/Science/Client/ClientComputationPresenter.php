<?php

namespace App\Services\Ai\Science\Client;

use App\Models\AiChatRunStep;
use Illuminate\Support\Str;

/** Called only inside owner-scoped chat responses/views; never exposes continuation tokens. */
final class ClientComputationPresenter
{
    public static function title(array $payload): string
    {
        $tasks = $payload['partial_scope']['completed_tasks'] ?? [];
        $candidates = [$payload['task_label'] ?? null,
            is_array($tasks) ? implode('; ', array_filter($tasks, 'is_string')) : null,
            $payload['model'] ?? null, $payload['science_request']['problem'] ?? null];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $normalized = preg_replace('/\s+/u', ' ', trim($candidate)) ?? trim($candidate);

                return Str::limit(Str::words($normalized, 3, ''), 48, '', preserveWords: true);
            }
        }

        return 'Perhitungan lokal';
    }

    /** Number actual client-script steps once per run, including legacy stored results. */
    public static function count(iterable $steps): int
    {
        $count = 0;
        foreach ($steps as $step) {
            $count += ($step->public_metadata['execution_mode'] ?? null) === 'client_script' ? 1 : 0;
        }

        return $count;
    }

    public static function labels(iterable $steps): array
    {
        $labels = [];
        $number = 0;
        foreach ($steps as $step) {
            $labels[$step->id] = $step->label;
            if (($step->public_metadata['execution_mode'] ?? null) !== 'client_script') {
                continue;
            }
            $payload = $step->private_payload ?? [];
            $title = self::title(['task_label' => $step->public_metadata['computation_title'] ?? null, ...$payload]);
            $outcome = self::status($step) === 'failed' ? 'gagal' : ($step->status === 'running' ? 'menjalankan' :
                (($payload['status'] ?? null) === 'client_computed' ? 'belum terverifikasi' : 'tidak menghasilkan jawaban'));
            if ($step->status === 'running' && str_starts_with($step->label, 'Menunggu persetujuan')) {
                $outcome = 'menunggu persetujuan';
            }
            $labels[$step->id] = 'Skrip '.(++$number).' · '.$title.' · '.$outcome;
        }

        return $labels;
    }

    /** Derive legacy display state without rewriting historical execution records. */
    public static function status(AiChatRunStep $step): string
    {
        if (($step->public_metadata['execution_mode'] ?? null) === 'client_script'
            && $step->status !== 'running'
            && (($step->private_payload['execution']['client_failure'] ?? null) !== null
                || ($step->public_metadata['execution_phase'] ?? null) === 'client_failed')) {
            return 'failed';
        }

        return $step->status;
    }

    public static function failureMessage(?string $code): ?string
    {
        return match ($code) {
            null => null,
            'syntax_error' => 'Skrip gagal: sintaks JavaScript tidak valid.',
            'timeout' => 'Skrip gagal: batas waktu komputasi terlampaui.',
            'cancelled' => 'Komputasi dibatalkan.',
            'unavailable' => 'Lingkungan komputasi lokal tidak tersedia.',
            'invalid_input' => 'Input, hasil, atau pemeriksaan skrip tidak memenuhi kontrak.',
            default => 'Skrip gagal dijalankan; tidak ada hasil komputasi.',
        };
    }

    public static function details(?array $payload): ?array
    {
        if (($payload['execution_mode'] ?? null) !== 'client_script' || ! is_array($payload['client_program'] ?? null)) {
            return null;
        }

        $failure = $payload['execution']['client_failure'] ?? null;

        return ['failure_code' => $failure, 'failure_message' => self::failureMessage($failure), 'description' => is_string($payload['model'] ?? null) ? $payload['model'] : '',
            'source' => $payload['client_program']['source'], 'inputs' => $payload['client_program']['inputs'],
            'program_hash' => $payload['client_program']['hash'], 'result' => $payload['result'] ?? null,
            'verification' => 'client_reported_only'];
    }
}
