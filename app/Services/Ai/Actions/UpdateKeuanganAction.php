<?php

namespace App\Services\Ai\Actions;

use App\Actions\Keuangan\SaveKeuanganAction;
use App\Models\Keuangan;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class UpdateKeuanganAction implements AiWriteAction
{
    public function __construct(
        private readonly SaveKeuanganAction $saveKeuangan,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'update_keuangan';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'keuangan_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'kategori' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $transaction = Keuangan::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($validated['keuangan_id']);
        $this->freshness->assertUnchanged($transaction, $validated['expected_updated_at'], 'Transaksi');
        unset($validated['keuangan_id'], $validated['expected_updated_at']);

        return ($this->saveKeuangan)($transaction, (int) $user->id, $validated);
    }
}
