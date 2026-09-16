<?php

namespace App\Services\Ai\Tools;

use App\Models\Keuangan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class SearchFinancialTransactionsTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'search_financial_transactions';
    }

    public function description(): string
    {
        return 'Mencari transaksi keuangan milik pengguna berdasarkan teks, kategori, jenis, nominal, atau rentang tanggal. Mengembalikan ID untuk koreksi yang presisi.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'query' => ['type' => 'string'], 'kategori' => ['type' => 'string'],
                'jenis' => ['type' => 'string', 'enum' => ['pemasukan', 'pengeluaran']],
                'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'min_nominal' => ['type' => 'number'], 'max_nominal' => ['type' => 'number'],
                'limit' => ['type' => 'integer', 'description' => 'Maksimal 50, default 20'],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $limit = min(50, max(1, (int) ($arguments['limit'] ?? 20)));
        $query = Keuangan::query()->where('user_id', $user->id);
        $text = trim((string) ($arguments['query'] ?? ''));
        if ($text !== '') {
            $query->where(fn ($builder) => $builder->where('deskripsi', 'like', '%'.$text.'%')->orWhere('kategori', 'like', '%'.$text.'%'));
        }
        if (! empty($arguments['kategori'])) {
            $query->where('kategori', 'like', '%'.trim((string) $arguments['kategori']).'%');
        }
        if (! empty($arguments['jenis'])) {
            $query->where('jenis', $arguments['jenis']);
        }
        if (! empty($arguments['start_date'])) {
            $query->whereDate('tanggal', '>=', $this->timeContext->parse($user, $arguments['start_date'])->toDateString());
        }
        if (! empty($arguments['end_date'])) {
            $query->whereDate('tanggal', '<=', $this->timeContext->parse($user, $arguments['end_date'])->toDateString());
        }
        if (isset($arguments['min_nominal'])) {
            $query->where('nominal', '>=', (float) $arguments['min_nominal']);
        }
        if (isset($arguments['max_nominal'])) {
            $query->where('nominal', '<=', (float) $arguments['max_nominal']);
        }

        $total = (clone $query)->count();
        $items = $query->latest('tanggal')->latest('id')->limit($limit)->get(['id', 'jenis', 'kategori', 'deskripsi', 'nominal', 'tanggal']);

        return ['total' => $total, 'returned' => $items->count(), 'truncated' => $total > $items->count(), 'transactions' => $items->map(fn (Keuangan $item) => [
            'id' => $item->id, 'type' => $item->jenis, 'category' => $item->kategori,
            'description' => $item->deskripsi, 'amount' => (float) $item->nominal,
            'date' => $item->tanggal->toDateString(),
        ])->all()];
    }
}
