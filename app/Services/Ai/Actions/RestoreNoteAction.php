<?php

namespace App\Services\Ai\Actions;

use App\Actions\Catatan\RestoreCatatanAction;
use App\Models\Catatan;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class RestoreNoteAction implements AiWriteAction
{
    public function __construct(
        private readonly RestoreCatatanAction $restoreNote,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'restore_note';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'catatan_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $note = Catatan::query()->where('user_id', $user->id)->where('status_sampah', true)
            ->lockForUpdate()->findOrFail($validated['catatan_id']);
        $this->freshness->assertUnchanged($note, $validated['expected_updated_at'], 'Catatan');
        ($this->restoreNote)($note);

        return $note->fresh();
    }
}
