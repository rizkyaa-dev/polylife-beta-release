<?php

namespace App\Services\Ai;

use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ReminderTargetResolver
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    /** @param  null|callable(Builder): void  $scope */
    public function resolve(User $user, string $type, string $search, ?callable $scope = null): Model
    {
        $definition = match ($type) {
            'todolist' => [Todolist::class, 'nama_item'],
            'tugas' => [Tugas::class, 'nama_tugas'],
            'jadwal' => [Jadwal::class, 'title'],
            'kegiatan' => [Kegiatan::class, 'nama_kegiatan'],
            default => throw new AiActionException("Jenis target reminder '{$type}' tidak didukung."),
        };

        [$model, $column] = $definition;
        $query = $model::query();
        if ($type === 'kegiatan') {
            $query->whereHas('jadwal', fn (Builder $builder) => $builder->where('user_id', $user->id));
        } else {
            $query->where('user_id', $user->id);
        }
        if ($scope !== null) {
            $scope($query);
        }

        return $this->resolver->resolveFromOwnedQuery($query, [$column], $search, "target reminder {$type}");
    }
}
