<?php

namespace App\Services\Ai\Tools;

use App\Models\AffiliationBroadcast;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use Illuminate\Support\Str;

final class GetAnnouncementsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_announcements';
    }

    public function description(): string
    {
        return 'Mengambil pengumuman kampus terbaru yang dipublikasikan dan boleh dilihat pengguna.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'Maksimal hasil, default 5'],
            ]],
        ];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $limit = min(10, max(1, (int) ($arguments['limit'] ?? 5)));
        $items = AffiliationBroadcast::query()->visibleToUser($user)
            ->latest('published_at')->limit($limit)
            ->get(['id', 'title', 'body', 'published_at'])
            ->map(fn (AffiliationBroadcast $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'body' => Str::limit(Str::squish(strip_tags($item->body)), 1200),
                'published_at' => $item->published_at?->toIso8601String(),
            ])->all();

        return ['count' => count($items), 'announcements' => $items];
    }
}
