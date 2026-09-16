<?php

namespace App\Services\Ai\Tools;

use App\Models\Catatan;
use App\Models\CatatanSearchToken;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Catatan\CatatanSearchIndexer;
use Illuminate\Support\Str;

final class SearchCatatanTool implements AiToolInterface
{
    public function __construct(private readonly CatatanSearchIndexer $indexer) {}

    public function name(): string
    {
        return 'search_catatan';
    }

    public function description(): string
    {
        return 'Mencari dan membaca catatan aktif milik pengguna berdasarkan judul atau isi.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Kata kunci catatan yang dicari'],
                    'limit' => ['type' => 'integer', 'description' => 'Maksimal hasil, default 5'],
                ],
                'required' => ['query'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '') {
            throw new AiActionException('Kata kunci pencarian catatan wajib diisi.');
        }
        $limit = min(10, max(1, (int) ($arguments['limit'] ?? 5)));
        $hashes = $this->indexer->hashesForSearch($query);

        $contentIds = $hashes === [] ? [] : CatatanSearchToken::query()
            ->where('user_id', $user->id)
            ->whereIn('token_hash', $hashes)
            ->groupBy('catatan_id')
            ->havingRaw('COUNT(DISTINCT token_hash) = ?', [count($hashes)])
            ->pluck('catatan_id')
            ->all();

        $notes = Catatan::query()
            ->where('user_id', $user->id)
            ->where('status_sampah', false)
            ->where(function ($builder) use ($query, $contentIds): void {
                $builder->where('judul', 'like', '%'.$query.'%');
                if ($contentIds !== []) {
                    $builder->orWhereIn('id', $contentIds);
                }
            })
            ->latest('tanggal')
            ->limit($limit)
            ->get(['id', 'judul', 'isi', 'tanggal'])
            ->map(fn (Catatan $note): array => [
                'id' => $note->id,
                'judul' => $note->judul,
                'isi' => Str::limit(Str::squish(strip_tags($note->isi)), 1200),
                'tanggal' => $note->tanggal?->toDateString(),
            ])->all();

        return ['query' => $query, 'count' => count($notes), 'catatan' => $notes];
    }
}
