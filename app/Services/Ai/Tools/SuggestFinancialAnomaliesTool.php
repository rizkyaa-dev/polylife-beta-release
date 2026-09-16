<?php

namespace App\Services\Ai\Tools;

use App\Models\Keuangan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class SuggestFinancialAnomaliesTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'suggest_financial_anomalies';
    }

    public function description(): string
    {
        return 'Mendeteksi kandidat pengeluaran tidak biasa berdasarkan riwayat kategori. Hanya advisory dan tidak mengubah transaksi.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'lookback_days' => ['type' => 'integer', 'description' => '30-365 hari, default 90'],
                'multiplier' => ['type' => 'number', 'description' => 'Ambang terhadap rata-rata kategori, 1.5-5, default 2'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $days = min(365, max(30, (int) ($arguments['lookback_days'] ?? 90)));
        $multiplier = min(5.0, max(1.5, (float) ($arguments['multiplier'] ?? 2.0)));
        $since = $this->timeContext->now($user)->subDays($days)->toDateString();
        $transactions = Keuangan::query()->where('user_id', $user->id)->where('jenis', 'pengeluaran')
            ->whereDate('tanggal', '>=', $since)->orderByDesc('tanggal')->limit(500)->get();
        $averages = $transactions->groupBy('kategori')->map(fn ($group): float => (float) $group->avg('nominal'));
        $candidates = $transactions->filter(function (Keuangan $transaction) use ($averages, $multiplier): bool {
            $average = (float) ($averages[$transaction->kategori] ?? 0);

            return $average > 0 && (float) $transaction->nominal >= $average * $multiplier;
        })->map(fn (Keuangan $transaction): array => [
            'transaction_id' => $transaction->id,
            'date' => $transaction->tanggal->toDateString(),
            'category' => $transaction->kategori,
            'description' => $transaction->deskripsi,
            'amount' => (float) $transaction->nominal,
            'category_average' => round((float) $averages[$transaction->kategori], 2),
            'ratio_to_average' => round((float) $transaction->nominal / max(1, (float) $averages[$transaction->kategori]), 2),
        ])->values()->take(30)->all();

        return [
            'lookback_days' => $days,
            'transactions_analyzed' => $transactions->count(),
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'warning' => 'Anomali adalah sinyal statistik sederhana, bukan bukti transaksi salah atau fraud.',
        ];
    }
}
