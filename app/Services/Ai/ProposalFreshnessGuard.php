<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Model;

final class ProposalFreshnessGuard
{
    public function assertUnchanged(Model $record, string $expectedUpdatedAt, string $label): void
    {
        if ($record->updated_at?->format('Y-m-d H:i:s') !== $expectedUpdatedAt) {
            throw new AiActionException("{$label} telah berubah setelah proposal dibuat. Buat proposal baru agar perubahan terbaru tidak tertimpa.");
        }
    }
}
