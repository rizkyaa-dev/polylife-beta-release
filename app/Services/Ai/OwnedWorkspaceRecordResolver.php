<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OwnedWorkspaceRecordResolver
{
    /**
     * @param  class-string<Model>  $model
     * @param  non-empty-list<string>  $columns
     * @param  null|callable(Builder): void  $scope
     */
    public function resolve(User $user, string $model, array $columns, string $search, string $label, ?callable $scope = null): Model
    {
        $base = $model::query()->where('user_id', $user->id);
        if ($scope) {
            $scope($base);
        }

        return $this->resolveFromOwnedQuery($base, $columns, $search, $label);
    }

    /**
     * Resolve a record from a query whose ownership boundary was already applied by the caller.
     *
     * @param  non-empty-list<string>  $columns
     */
    public function resolveFromOwnedQuery(Builder $base, array $columns, string $search, string $label): Model
    {
        $search = trim($search);
        if ($search === '') {
            throw new AiActionException("Nama {$label} wajib diisi.");
        }

        $exact = (clone $base)->where(function (Builder $query) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $query->{$method}("LOWER({$column}) = ?", [mb_strtolower($search)]);
            }
        })->first();
        if ($exact) {
            return $exact;
        }

        $matches = $base->where(function (Builder $query) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', '%'.$search.'%');
            }
        })->limit(2)->get();

        if ($matches->isEmpty()) {
            throw new AiActionException(ucfirst($label)." '{$search}' tidak ditemukan di workspace Anda.");
        }
        if ($matches->count() > 1) {
            throw new AiActionException(ucfirst($label)." '{$search}' ambigu. Gunakan nama yang lebih spesifik.");
        }

        return $matches->first();
    }
}
