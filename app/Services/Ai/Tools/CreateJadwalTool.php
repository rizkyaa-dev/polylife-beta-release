<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;

class CreateJadwalTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'create_jadwal';
    }

    public function description(): string
    {
        return 'Mengusulkan penambahan agenda atau jadwal perkuliahan baru ke kalender pengguna.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => 'Nama kegiatan atau mata kuliah',
                    ],
                    'type' => [
                        'type' => 'string',
                        'description' => 'Jenis jadwal: kuliah, libur, uts, uas, lomba, atau lainnya',
                    ],
                    'start_at' => [
                        'type' => 'string',
                        'description' => 'Waktu mulai format YYYY-MM-DD HH:MM:SS',
                    ],
                    'end_at' => [
                        'type' => 'string',
                        'description' => 'Waktu selesai format YYYY-MM-DD HH:MM:SS',
                    ],
                    'location' => [
                        'type' => 'string',
                        'description' => 'Lokasi ruangan atau tempat (opsional)',
                    ],
                    'notes' => [
                        'type' => 'string',
                        'description' => 'Catatan tambahan (opsional)',
                    ],
                ],
                'required' => ['title', 'type', 'start_at', 'end_at'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $title = trim((string) ($arguments['title'] ?? ''));
        $type = strtolower(trim((string) ($arguments['type'] ?? 'lainnya')));
        if (! in_array($type, ['kuliah', 'libur', 'uts', 'uas', 'lomba', 'lainnya'], true)) {
            $type = 'lainnya';
        }

        $startAt = isset($arguments['start_at'])
            ? $this->timeContext->parse($user, $arguments['start_at'])
            : $this->timeContext->now($user);
        $endAt = isset($arguments['end_at'])
            ? $this->timeContext->parse($user, $arguments['end_at'])
            : $startAt->addHour();
        $location = isset($arguments['location']) ? trim((string) $arguments['location']) : null;
        $notes = isset($arguments['notes']) ? trim((string) $arguments['notes']) : null;

        $summary = sprintf(
            'Jadwal: %s (%s) pada %s s/d %s%s',
            $title,
            ucfirst($type),
            $startAt->translatedFormat('d M Y H:i'),
            $endAt->format('H:i'),
            $location ? " di {$location}" : ''
        );

        return [
            'status' => 'proposal_created',
            'tool_name' => $this->name(),
            'summary' => $summary,
            'payload' => [
                'title' => $title,
                'jenis' => $type,
                'tanggal_mulai' => $startAt->toDateString(),
                'tanggal_selesai' => $endAt->toDateString(),
                'start_time' => $startAt->format('H:i:s'),
                'end_time' => $endAt->format('H:i:s'),
                'location' => $location,
                'catatan_tambahan' => $notes,
            ],
        ];
    }
}
