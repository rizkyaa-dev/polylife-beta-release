<?php

namespace App\Http\Controllers\Api;

use App\Actions\Todolist\SaveTodolistAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Todolist\StoreTodolistRequest;
use App\Http\Requests\Todolist\UpdateTodolistRequest;
use App\Http\Resources\Api\TodolistResource;
use App\Models\Todolist;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodolistController extends Controller
{
    public function __construct(
        private readonly SaveTodolistAction $saveTodolistAction
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $items = Todolist::query()
            ->with(['reminders' => fn ($query) => $query->orderByDesc('aktif')->orderBy('waktu_reminder')])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => TodolistResource::collection($items)->resolve(),
            'meta' => [
                'total' => $items->count(),
                'ongoing_count' => $items->where('status', false)->count(),
                'completed_count' => $items->where('status', true)->count(),
            ],
        ]);
    }

    public function store(StoreTodolistRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $item = ($this->saveTodolistAction)(
            null,
            $request->user()->id,
            $validated,
            $request->boolean('status'),
            $request->boolean('reminder_enabled')
        );

        return response()->json([
            'message' => 'Todolist berhasil ditambahkan.',
            'data' => (new TodolistResource($item))->resolve(),
        ], 201);
    }

    public function update(UpdateTodolistRequest $request, int $todolist): JsonResponse
    {
        $item = $this->findOwnedOrFail($request, $todolist);
        $validated = $request->validated();

        $item = ($this->saveTodolistAction)(
            $item,
            $request->user()->id,
            $validated,
            $request->boolean('status'),
            $request->boolean('reminder_enabled')
        );

        return response()->json([
            'message' => 'Todolist berhasil diperbarui.',
            'data' => (new TodolistResource($item))->resolve(),
        ]);
    }

    public function destroy(Request $request, int $todolist): JsonResponse
    {
        $item = $this->findOwnedOrFail($request, $todolist);
        $item->reminders()->delete();
        $item->delete();

        return response()->json([
            'message' => 'Todolist berhasil dihapus.',
        ]);
    }

    private function findOwnedOrFail(Request $request, int $id): Todolist
    {
        $item = Todolist::query()
            ->with(['reminders' => fn ($query) => $query->orderByDesc('aktif')->orderBy('waktu_reminder')])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $item) {
            throw (new ModelNotFoundException())->setModel(Todolist::class, [$id]);
        }

        return $item;
    }
}
