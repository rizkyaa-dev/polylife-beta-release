<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Todolist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TodolistController extends Controller
{
    public function index()
    {
        $todolists = Todolist::with('reminders')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('todolist.index', compact('todolists'));
    }

    public function create()
    {
        return view('todolist.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_item' => 'required|string|max:150',
            'status' => 'nullable|boolean',
            'reminder_enabled' => 'nullable|boolean',
            'reminder_date' => [
                Rule::requiredIf(fn () => $request->boolean('reminder_enabled')),
                'nullable',
                'date',
            ],
            'reminder_time' => [
                Rule::requiredIf(fn () => $request->boolean('reminder_enabled')),
                'nullable',
                'date_format:H:i',
            ],
        ]);

        $todolist = Todolist::create([
            'user_id' => Auth::id(),
            'nama_item' => $validated['nama_item'],
            'status' => $request->boolean('status'),
        ]);

        if ($request->boolean('reminder_enabled')) {
            $reminderDateTime = Carbon::parse(
                $validated['reminder_date'] . ' ' . ($validated['reminder_time'] ?? '00:00')
            );

            Reminder::create([
                'user_id' => Auth::id(),
                'todolist_id' => $todolist->id,
                'waktu_reminder' => $reminderDateTime,
                'aktif' => true,
            ]);
        }

        return redirect()->route('todolist.index')->with('success', 'Item to-do berhasil ditambahkan.');
    }

    public function edit(Todolist $todolist)
    {
        $this->authorizeAccess($todolist);
        $todolist->load('reminders');
        return view('todolist.edit', compact('todolist'));
    }

    public function update(Request $request, Todolist $todolist)
    {
        $this->authorizeAccess($todolist);

        $validated = $request->validate([
            'nama_item' => 'required|string|max:150',
            'status' => 'nullable|boolean',
            'reminder_enabled' => 'nullable|boolean',
            'reminder_date' => [
                Rule::requiredIf(fn () => $request->boolean('reminder_enabled')),
                'nullable',
                'date',
            ],
            'reminder_time' => [
                Rule::requiredIf(fn () => $request->boolean('reminder_enabled')),
                'nullable',
                'date_format:H:i',
            ],
        ]);

        $status = $request->boolean('status');

        $todolist->update([
            'nama_item' => $validated['nama_item'],
            'status' => $status,
        ]);

        $reminderEnabled = $request->boolean('reminder_enabled');
        $existingReminder = $todolist->reminders()->first();

        if ($reminderEnabled) {
            $reminderDateTime = Carbon::parse(
                $validated['reminder_date'] . ' ' . ($validated['reminder_time'] ?? '00:00')
            );

            if ($existingReminder) {
                $existingReminder->update([
                    'waktu_reminder' => $reminderDateTime,
                    'aktif' => true,
                ]);
            } else {
                Reminder::create([
                    'user_id' => Auth::id(),
                    'todolist_id' => $todolist->id,
                    'waktu_reminder' => $reminderDateTime,
                    'aktif' => true,
                ]);
            }
        } elseif ($existingReminder) {
            $existingReminder->update([
                'aktif' => false,
            ]);
        }

        return redirect()->route('todolist.index')->with('success', 'Item to-do berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, Todolist $todolist)
    {
        $this->authorizeAccess($todolist);

        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $newStatus = $request->boolean('status');

        $todolist->update([
            'status' => $newStatus,
        ]);

        $todolist->reminders()->update([
            'aktif' => $newStatus ? false : true,
        ]);

        $tab = $newStatus ? 'completed' : 'ongoing';

        $todolist->load('reminders');

        if ($request->expectsJson()) {
            $metaMessage = $newStatus
                ? 'Ditandai selesai - akan hilang dalam 10 menit.'
                : 'Centang untuk menandai selesai.';

            return response()->json([
                'status' => $newStatus,
                'tab' => $tab,
                'badge' => $this->reminderBadge($todolist),
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

    private function authorizeAccess(Todolist $todolist)
    {
        if ($todolist->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }

    private function reminderBadge(Todolist $todolist): array
    {
        $hasReminder = $todolist->reminders->isNotEmpty();
        $hasActiveReminder = $todolist->reminders->where('aktif', true)->isNotEmpty();

        if (! $hasReminder) {
            return [
                'text' => 'Tanpa reminder',
                'classes' => 'bg-gray-100 text-gray-600',
            ];
        }

        if ($hasActiveReminder) {
            return [
                'text' => 'Reminder aktif',
                'classes' => 'bg-indigo-50 text-indigo-700',
            ];
        }

        return [
            'text' => 'Reminder nonaktif',
            'classes' => 'bg-amber-50 text-amber-700',
        ];
    }
}
