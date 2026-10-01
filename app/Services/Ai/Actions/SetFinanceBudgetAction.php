<?php

namespace App\Services\Ai\Actions;

use App\Models\KeuanganBudget;
use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class SetFinanceBudgetAction implements AiWriteAction
{
    public function __construct(private readonly ProposalFreshnessGuard $freshness) {}

    public function toolName(): string
    {
        return 'set_finance_budget';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'expected_record_hash' => ProposalFreshnessGuard::validationRules(),
            'kategori' => ['required', 'string', 'max:100'],
            'nominal_limit' => ['required', 'numeric', 'min:1000'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);

        $identity = ['user_id' => $user->id, 'kategori' => trim($validated['kategori']), 'bulan' => $validated['bulan'], 'tahun' => $validated['tahun']];
        try {
            return DB::transaction(function () use ($identity, $validated): KeuanganBudget {
                $existing = KeuanganBudget::query()->where($identity)->lockForUpdate()->first();
                $this->freshness->assertUnchanged($existing, $validated['expected_record_hash'], 'Anggaran');
                if ($existing !== null) {
                    $existing->update(['nominal_limit' => $validated['nominal_limit']]);

                    return $existing->fresh();
                }

                // Insert, never upsert: a competing creation must not be overwritten.
                return KeuanganBudget::query()->create($identity + ['nominal_limit' => $validated['nominal_limit']]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new AiActionException('Anggaran telah dibuat setelah proposal. Buat proposal baru.');
        }
    }
}
