<?php

namespace App\Services\Ai\Actions;

use App\Actions\Catatan\SaveCatatanAction;
use App\Models\Catatan;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class UpdateCatatanAction implements AiWriteAction
{
    public function __construct(private readonly SaveCatatanAction $saveCatatan, private readonly ProposalFreshnessGuard $freshness) {}

    public function toolName(): string
    {
        return 'update_catatan';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'catatan_id' => ['required', 'integer'],
            'judul' => ['required', 'string', 'max:150'],
            'isi' => ['required', 'string'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'show_preview' => ['required', 'boolean'],
            'expected_record_hash' => ProposalFreshnessGuard::validationRules(),
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $note = Catatan::query()->where('user_id', $user->id)->where('status_sampah', false)
            ->lockForUpdate()->findOrFail($validated['catatan_id']);
        $this->freshness->assertUnchanged($note, $validated['expected_record_hash'], 'Catatan');

        unset($validated['catatan_id'], $validated['expected_record_hash']);

        return ($this->saveCatatan)($note, (int) $user->id, $validated);
    }
}
