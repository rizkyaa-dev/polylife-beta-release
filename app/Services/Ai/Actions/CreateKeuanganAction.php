<?php

namespace App\Services\Ai\Actions;

use App\Actions\Keuangan\SaveKeuanganAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class CreateKeuanganAction implements AiWriteAction
{
    public function __construct(
        private readonly SaveKeuanganAction $saveKeuangan
    ) {}

    public function toolName(): string
    {
        return 'create_keuangan';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'kategori' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal' => ['required', 'date'],
            'deskripsi' => ['nullable', 'string'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);

        return ($this->saveKeuangan)(null, (int) $user->id, $validated);
    }
}
