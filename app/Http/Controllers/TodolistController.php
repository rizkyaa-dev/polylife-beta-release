<?php

namespace App\Http\Controllers;

use App\Actions\Todolist\SaveTodolistAction;
use App\Actions\Todolist\ToggleTodolistStatusAction;
use App\Http\Requests\Todolist\StoreTodolistRequest;
use App\Http\Requests\Todolist\ToggleTodolistStatusRequest;
use App\Http\Requests\Todolist\UpdateTodolistRequest;
use App\Models\Todolist;
use App\Services\Reminder\TodolistReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TodolistController extends Controller
{
    public function __construct(
        private readonly SaveTodolistAction $saveTodolistAction,
        private readonly ToggleTodolistStatusAction $toggleTodolistStatusAction,
        private readonly TodolistReminderService $todolistReminderService
    ) {
    }

    public function index()
    {
        $todolists = Todolist::query()
            ->with('reminders')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('todolist.index', compact('todolists'));
    }

    public function create()
    {
        return view('todolist.create');
    }

    public function store(StoreTodolistRequest $request)
    {
        ($this->saveTodolistAction)(
            null,
            Auth::id(),
            $request->validated(),
            $request->boolean('status'),
            $request->boolean('reminder_enabled')
        );

        return redirect()->route('todolist.index')->with('success', 'Item to-do berhasil ditambahkan.');
    }

    public function edit(Todolist $todolist)
    {
        $this->authorizeAccess($todolist);
        $todolist->load('reminders');

        return view('todolist.edit', compact('todolist'));
    }

    public function update(UpdateTodolistRequest $request, Todolist $todolist)
    {
        $this->authorizeAccess($todolist);
        ($this->saveTodolistAction)(
            $todolist,
            Auth::id(),
            $request->validated(),
            $request->boolean('status'),
            $request->boolean('reminder_enabled')
        );

        return redirect()->route('todolist.index')->with('success', 'Item to-do berhasil diperbarui.');
    }

    public function toggleStatus(ToggleTodolistStatusRequest $request, Todolist $todolist)
    {
        $this->authorizeAccess($todolist);
        $newStatus = $request->boolean('status');
        $todolist = ($this->toggleTodolistStatusAction)($todolist, $newStatus);

        $tab = $newStatus ? 'completed' : 'ongoing';

        if ($request->expectsJson()) {
            $metaMessage = $newStatus
                ? 'Ditandai selesai - akan hilang dalam 10 menit.'
                : 'Centang untuk menandai selesai.';

            return response()->json([
                'status' => $newStatus,
                'tab' => $tab,
                'badge' => $this->todolistReminderService->badgeFor($todolist),
                'timestamp' => $newStatus
                    ? 'Selesai ' . ($todolist->updated_at?->diffForHumans() ?? '')
                    : 'Dibuat ' . ($todolist->created_at?->diffForHumans() ?? ''),
                'meta' => $metaMessage,
                'visible_for_seconds' => $newStatus ? 600 : null,
                'completed_at' => $todolist->updated_at?->toIso8601String(),
            ]);
        }

        return redirect()->route('todolist.index', ['tab' => $tab]);
    }

    public function destroy(Todolist $todolist)
    {
        $this->authorizeAccess($todolist);
        $todolist->delete();

        return redirect()->route('todolist.index')->with('success', 'Item to-do berhasil dihapus.');
    }

    private function authorizeAccess(Todolist $todolist): void
    {
        if ((int) $todolist->user_id !== (int) Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
