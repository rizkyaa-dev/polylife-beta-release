<?php

namespace App\Services\Ai\Tools;

use App\Models\Keuangan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\UserTimeContext;

final class UpdateKeuanganTool implements AiToolInterface
{
    public function __construct(
        private readonly OwnedWorkspaceRecordResolver $resolver,
        private readonly UserTimeContext $timeContext
    ) {}

    public function name(): string
    {
        return 'update_keuangan';
    }

    public function description(): string
    {
        return 'Mengusulkan koreksi transaksi keuangan yang sudah ada berdasarkan ID hasil pencarian atau deskripsi/kategori yang unik.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'transaction_id' => ['type' => 'integer'],
                'target_query' => ['type' => 'string', 'description' => 'Deskripsi atau kategori transaksi'],
                'jenis' => ['type' => 'string', 'enum' => ['pemasukan', 'pengeluaran']],
                'kategori' => ['type' => 'string'],
                'deskripsi' => ['type' => 'string'],
                'nominal' => ['type' => 'number'],
                'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            ]],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $transaction = isset($arguments['transaction_id'])
            ? Keuangan::query()->where('user_id', $user->id)->find($arguments['transaction_id'])
            : $this->resolver->resolve($user, Keuangan::class, ['deskripsi', 'kategori'], (string) ($arguments['target_query'] ?? ''), 'transaksi');
        if (! $transaction) {
            throw new AiActionException('Transaksi tidak ditemukan di workspace Anda.');
        }
        if (collect($arguments)->except(['transaction_id', 'target_query'])->isEmpty()) {
            throw new AiActionException('Sebutkan bagian transaksi yang ingin diubah.');
        }

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => 'Koreksi transaksi #'.$transaction->id.' '.$transaction->kategori,
            'payload' => [
                'keuangan_id' => $transaction->id,
                'expected_updated_at' => $transaction->updated_at->format('Y-m-d H:i:s'),
                'jenis' => $arguments['jenis'] ?? $transaction->jenis,
                'kategori' => array_key_exists('kategori', $arguments) ? trim((string) $arguments['kategori']) : $transaction->kategori,
                'deskripsi' => array_key_exists('deskripsi', $arguments) ? trim((string) $arguments['deskripsi']) ?: null : $transaction->deskripsi,
                'nominal' => array_key_exists('nominal', $arguments) ? (float) $arguments['nominal'] : (float) $transaction->nominal,
                'tanggal' => array_key_exists('tanggal', $arguments) ? $this->timeContext->parse($user, $arguments['tanggal'])->toDateString() : $transaction->tanggal->toDateString(),
            ],
        ];
    }
}
