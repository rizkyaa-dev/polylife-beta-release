<?php

namespace App\Services\Ai\Tools;

use App\Models\Catatan;
use App\Models\Jadwal;
use App\Models\Keuangan;
use App\Models\Matkul;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DetectDuplicateRecordsTool implements AiToolInterface
{
    public function name(): string
    {
        return 'detect_duplicate_records';
    }

    public function description(): string
    {
        return 'Mendeteksi kandidat record duplikat exact-normalized milik pengguna. Tidak menghapus atau menggabungkan data.';
    }

    public function schema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => [
            'type' => 'object', 'properties' => [
                'domain' => ['type' => 'string', 'enum' => ['all', 'notes', 'tasks', 'todos', 'finances', 'schedules', 'courses']],
            ],
        ]];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $requested = (string) ($arguments['domain'] ?? 'all');
        $loaders = [
            'notes' => fn () => Catatan::query()->where('user_id', $user->id)->where('status_sampah', false)->limit(300)->get()->map(fn ($row) => [$row->id, $row->judul, $this->key($row->judul)]),
            'tasks' => fn () => Tugas::query()->where('user_id', $user->id)->limit(300)->get()->map(fn ($row) => [$row->id, $row->nama_tugas, $this->key($row->nama_tugas.'|'.$row->deadline)]),
            'todos' => fn () => Todolist::query()->where('user_id', $user->id)->limit(300)->get()->map(fn ($row) => [$row->id, $row->nama_item, $this->key($row->nama_item)]),
            'finances' => fn () => Keuangan::query()->where('user_id', $user->id)->limit(300)->get()->map(fn ($row) => [$row->id, $row->deskripsi, $this->key($row->jenis.'|'.$row->kategori.'|'.$row->nominal.'|'.$row->tanggal.'|'.$row->deskripsi)]),
            'schedules' => fn () => Jadwal::query()->where('user_id', $user->id)->limit(300)->get()->map(fn ($row) => [$row->id, $row->title, $this->key($row->jenis.'|'.$row->title.'|'.$row->tanggal_mulai.'|'.$row->start_time)]),
            'courses' => fn () => Matkul::query()->ownedBy((int) $user->id)->limit(300)->get()->map(fn ($row) => [$row->id, $row->kode.' '.$row->nama, $this->key($row->kode.'|'.$row->semester.'|'.$row->kelas)]),
        ];
        $domains = $requested === 'all' ? array_keys($loaders) : [$requested];
        $duplicates = [];
        foreach ($domains as $domain) {
            /** @var Collection<int, array{int, string, string}> $records */
            $records = $loaders[$domain]();
            foreach ($records->groupBy(fn (array $row): string => $row[2])->filter(fn (Collection $group): bool => $group->count() > 1) as $group) {
                $duplicates[] = [
                    'domain' => $domain,
                    'label' => $group->first()[1],
                    'record_ids' => $group->pluck(0)->values()->all(),
                    'confidence' => 'exact_normalized',
                ];
            }
        }

        return ['candidate_group_count' => count($duplicates), 'candidates' => array_slice($duplicates, 0, 50), 'destructive_action_taken' => false];
    }

    private function key(mixed $value): string
    {
        return Str::lower(Str::squish((string) $value));
    }
}
