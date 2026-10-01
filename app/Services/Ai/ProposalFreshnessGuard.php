<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Model;

final class ProposalFreshnessGuard
{
    /** Bind a proposal to the persisted row, including edits within one timestamp tick. */
    public static function snapshot(?Model $record): string
    {
        if ($record === null) {
            return hash('sha256', 'record:absent');
        }
        $attributes = $record->getRawOriginal();
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    public static function validationRules(): array
    {
        return ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'];
    }

    public function assertUnchanged(?Model $record, string $expectedSnapshot, string $label): void
    {
        if (! hash_equals(self::snapshot($record), $expectedSnapshot)) {
            throw new AiActionException("{$label} telah berubah setelah proposal dibuat. Buat proposal baru agar perubahan terbaru tidak tertimpa.");
        }
    }
}
