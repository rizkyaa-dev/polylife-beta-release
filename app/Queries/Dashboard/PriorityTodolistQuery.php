<?php

namespace App\Queries\Dashboard;

use App\Models\Todolist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PriorityTodolistQuery
{
    /**
     * @return Collection<int, Todolist>
     */
    public function forUser(int $userId, Carbon $recentCompletionThreshold, int $limit = 8): Collection
    {
        $todos = Todolist::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($recentCompletionThreshold) {
                $query->where('status', false)
                    ->orWhere(function ($sub) use ($recentCompletionThreshold) {
                        $sub->where('status', true)
                            ->where('updated_at', '>=', $recentCompletionThreshold);
                    });
            })
            ->orderBy('status')
            ->orderByDesc('updated_at')
            ->take($limit)
            ->get();

        $todos->each(function (Todolist $todo) use ($recentCompletionThreshold) {
            $todo->recently_completed = $todo->status && $todo->updated_at >= $recentCompletionThreshold;
        });

        return $todos;
    }
}
