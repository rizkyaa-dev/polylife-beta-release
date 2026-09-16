<?php

namespace App\Services\Ai\Tools;

use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class SearchSchedulesTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'search_schedules';
    }

    public function description(): string
    {
        return 'Mencari jadwal lama atau mendatang berdasarkan judul, catatan, lokasi, jenis, status, atau rentang tanggal. Mengembalikan ID canonical.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'query' => ['type' => 'string'],
                'jenis' => ['type' => 'string', 'enum' => ['kuliah', 'libur', 'uts', 'uas', 'lomba', 'lainnya']],
                'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'status' => ['type' => 'string', 'enum' => ['all', 'pending', 'completed']],
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
        $query = Jadwal::query()->where('user_id', $user->id)->withCount('kegiatans');
        $text = trim((string) ($arguments['query'] ?? ''));
        if ($text !== '') {
            $query->where(fn ($builder) => $builder->where('title', 'like', '%'.$text.'%')
                ->orWhere('location', 'like', '%'.$text.'%')->orWhere('catatan_tambahan', 'like', '%'.$text.'%'));
        }
        if (! empty($arguments['jenis'])) {
            $query->where('jenis', $arguments['jenis']);
        }
        if (! empty($arguments['start_date'])) {
            $query->whereDate('tanggal_selesai', '>=', $this->timeContext->parse($user, $arguments['start_date'])->toDateString());
        }
        if (! empty($arguments['end_date'])) {
            $query->whereDate('tanggal_mulai', '<=', $this->timeContext->parse($user, $arguments['end_date'])->toDateString());
        }
        if (($arguments['status'] ?? 'all') === 'pending') {
            $query->where('is_completed', false);
        } elseif (($arguments['status'] ?? 'all') === 'completed') {
            $query->where('is_completed', true);
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('tanggal_mulai')->orderBy('start_time')->limit($limit)->get();

        return ['total' => $total, 'returned' => $items->count(), 'truncated' => $total > $items->count(), 'schedules' => $items->map(fn (Jadwal $item) => [
            'id' => $item->id, 'title' => $item->title, 'type' => $item->jenis,
            'start_date' => $item->tanggal_mulai->toDateString(), 'end_date' => $item->tanggal_selesai->toDateString(),
            'start_time' => $item->start_time, 'end_time' => $item->end_time, 'location' => $item->location,
            'completed' => (bool) $item->is_completed, 'activity_count' => $item->kegiatans_count,
        ])->all()];
    }
}
