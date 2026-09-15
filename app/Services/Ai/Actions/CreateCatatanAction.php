<?php

namespace App\Services\Ai\Actions;

use App\Actions\Catatan\SaveCatatanAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class CreateCatatanAction implements AiWriteAction
{
    public function __construct(
        private readonly SaveCatatanAction $saveCatatan
    ) {}

    public function toolName(): string
    {
        return 'create_catatan';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'judul' => ['required', 'string', 'max:150'],
            'isi' => ['required', 'string'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'show_preview' => ['required', 'boolean'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        return ($this->saveCatatan)(null, (int) $user->id, $this->validatePayload($payload));
    }
}
