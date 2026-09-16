<?php

namespace App\Services\Ai\Actions;

use App\Models\KeuanganBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

final class SetFinanceBudgetAction implements AiWriteAction
{
    public function toolName(): string
    {
        return 'set_finance_budget';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'kategori' => ['required', 'string', 'max:100'],
            'nominal_limit' => ['required', 'numeric', 'min:1000'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);

        return KeuanganBudget::query()->updateOrCreate(
            ['user_id' => $user->id, 'kategori' => trim($validated['kategori']), 'bulan' => $validated['bulan'], 'tahun' => $validated['tahun']],
            ['nominal_limit' => $validated['nominal_limit']]
        );
    }
}
