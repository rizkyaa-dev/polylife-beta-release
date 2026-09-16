<?php

namespace App\Services\Ai\Actions;

use App\Actions\Catatan\SaveCatatanAction;
use App\Models\Catatan;
use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class UpdateCatatanAction implements AiWriteAction
{
    public function __construct(private readonly SaveCatatanAction $saveCatatan) {}

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
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $note = Catatan::query()->where('user_id', $user->id)->where('status_sampah', false)
            ->lockForUpdate()->findOrFail($validated['catatan_id']);
        if ($note->updated_at->format('Y-m-d H:i:s') !== $validated['expected_updated_at']) {
            throw new AiActionException('Catatan telah berubah setelah proposal dibuat. Buat proposal baru agar edit terbaru tidak tertimpa.');
        }

        unset($validated['catatan_id'], $validated['expected_updated_at']);

        return ($this->saveCatatan)($note, (int) $user->id, $validated);
    }
}
