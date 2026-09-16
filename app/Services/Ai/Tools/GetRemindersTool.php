<?php

namespace App\Services\Ai\Tools;

use App\Models\Reminder;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

final class GetRemindersTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'get_reminders';
    }

    public function description(): string
    {
        return 'Mengambil reminder pengguna beserta targetnya. Gunakan untuk menanyakan reminder aktif atau mendatang.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'include_inactive' => ['type' => 'boolean', 'description' => 'Sertakan reminder nonaktif, default false'],
                    'limit' => ['type' => 'integer', 'description' => 'Maksimal hasil, default 15'],
                ],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 15)));
        $items = Reminder::query()->where('user_id', $user->id)
            ->when(! ($arguments['include_inactive'] ?? false), fn ($query) => $query->where('aktif', true))
            ->where('waktu_reminder', '>=', $this->timeContext->now($user)->format('Y-m-d H:i:s'))
            ->with(['todolist:id,nama_item', 'tugas:id,nama_tugas', 'jadwal:id,title,jenis', 'kegiatan:id,nama_kegiatan'])
            ->orderBy('waktu_reminder')->limit($limit)->get()
            ->map(fn (Reminder $reminder): array => [
                'id' => $reminder->id,
                'target_type' => $this->targetType($reminder),
                'target_name' => $this->targetName($reminder),
                'waktu_reminder' => $this->timeContext
                    ->parse($user, $reminder->getRawOriginal('waktu_reminder'))
                    ->toIso8601String(),
                'aktif' => $reminder->aktif,
            ])->all();

        return ['count' => count($items), 'reminders' => $items];
    }

    private function targetType(Reminder $reminder): string
    {
        return $reminder->tugas_id ? 'tugas' : ($reminder->jadwal_id ? 'jadwal' : ($reminder->kegiatan_id ? 'kegiatan' : 'todolist'));
    }

    private function targetName(Reminder $reminder): string
    {
        return (string) ($reminder->tugas?->nama_tugas
            ?? $reminder->jadwal?->title
            ?? $reminder->kegiatan?->nama_kegiatan
            ?? $reminder->todolist?->nama_item
            ?? 'Target tidak tersedia');
    }
}
