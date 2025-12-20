<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\Jadwal;
use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class ReminderController extends Controller
{
    public function index()
    {
        $reminders = Reminder::where('user_id', Auth::id())
            ->with(['todolist', 'tugas', 'jadwal', 'kegiatan'])
            ->orderByDesc('waktu_reminder')
            ->get();

        return view('reminder.index', compact('reminders'));
    }

    public function create()
    {
        $userId = Auth::id();
        $payload = $this->loadReferenceData($userId);
        $payload['reminder'] = null;
        $payload['selectedTarget'] = 'todolist';
        return view('reminder.create', $payload);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reminder_target' => ['required', Rule::in(['todolist', 'tugas', 'jadwal', 'kegiatan'])],
            'todolist_id' => 'nullable|exists:todolists,id',
            'tugas_id' => 'nullable|exists:tugas,id',
            'jadwal_id' => 'nullable|exists:jadwals,id',
            'kegiatan_id' => 'nullable|exists:kegiatans,id',
            'waktu_reminder' => 'required|date',
            'aktif' => 'boolean',
        ]);

        $reminderData = $this->prepareReminderPayload($validated, $request);

        Reminder::create($reminderData);

        return redirect()->route('reminder.index')->with('success', 'Reminder berhasil ditambahkan.');
    }

    public function edit(Reminder $reminder)
    {
        $this->authorizeAccess($reminder);
        $userId = Auth::id();
        $payload = $this->loadReferenceData($userId);
        $payload['reminder'] = $reminder;
        $payload['selectedTarget'] = $this->resolveReminderTarget($reminder);
        return view('reminder.edit', $payload);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $this->authorizeAccess($reminder);

        $validated = $request->validate([
            'reminder_target' => ['required', Rule::in(['todolist', 'tugas', 'jadwal', 'kegiatan'])],
            'todolist_id' => 'nullable|exists:todolists,id',
            'tugas_id' => 'nullable|exists:tugas,id',
            'jadwal_id' => 'nullable|exists:jadwals,id',
            'kegiatan_id' => 'nullable|exists:kegiatans,id',
            'waktu_reminder' => 'required|date',
            'aktif' => 'boolean',
        ]);

        $reminderData = $this->prepareReminderPayload($validated, $request, $reminder);

        $reminder->update($reminderData);

        return redirect()->route('reminder.index')->with('success', 'Reminder berhasil diperbarui.');
    }

    public function destroy(Reminder $reminder)
    {
        $this->authorizeAccess($reminder);
        $reminder->delete();

        return redirect()->route('reminder.index')->with('success', 'Reminder berhasil dihapus.');
    }

    private function authorizeAccess(Reminder $reminder)
    {
        if ($reminder->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }

    private function loadReferenceData(int $userId): array
    {
        return [
            'todolists' => Todolist::where('user_id', $userId)->orderBy('nama_item')->get(),
            'tugasList' => Tugas::where('user_id', $userId)->orderBy('deadline')->get(),
            'jadwals' => Jadwal::where('user_id', $userId)->orderBy('tanggal_mulai')->get(),
            'kegiatans' => Kegiatan::with('jadwal')
                ->whereHas('jadwal', fn ($q) => $q->where('user_id', $userId))
                ->orderBy('tanggal_deadline')
                ->get(),
        ];
    }

    private function prepareReminderPayload(array $validated, Request $request, ?Reminder $reminder = null): array
    {
        $targetFields = ['todolist_id', 'tugas_id', 'jadwal_id', 'kegiatan_id'];
        $target = $validated['reminder_target'];
        $targetField = $target . '_id';

        foreach ($targetFields as $field) {
            $value = $request->input($field);
            $validated[$field] = ($field === $targetField) ? ($value ?: null) : null;
        }

        if (empty($validated[$targetField])) {
            abort(422, 'Target reminder harus dipilih.');
        }

        if ($validated[$targetField]) {
            $this->authorizeTargetOwnership($targetField, (int) $validated[$targetField]);
        }

        if (!$reminder) {
            $validated['user_id'] = Auth::id();
        }

        $validated['aktif'] = $request->has('aktif');
        $validated['waktu_reminder'] = Carbon::parse($validated['waktu_reminder'])->toDateTimeString();

        unset($validated['reminder_target']);

        return $validated;
    }

    private function resolveReminderTarget(Reminder $reminder): string
    {
        if ($reminder->tugas_id) {
            return 'tugas';
        }

        if ($reminder->jadwal_id) {
            return 'jadwal';
        }

        if ($reminder->kegiatan_id) {
            return 'kegiatan';
        }

        return 'todolist';
    }

    private function authorizeTargetOwnership(string $field, int $id): void
    {
        $map = [
            'todolist_id' => Todolist::class,
            'tugas_id' => Tugas::class,
            'jadwal_id' => Jadwal::class,
            'kegiatan_id' => Kegiatan::class,
        ];

        $model = $map[$field] ?? null;
        if (!$model) {
            return;
        }

        $record = $field === 'kegiatan_id'
            ? $model::with('jadwal')->find($id)
            : $model::find($id);
        if (!$record) {
            abort(404);
        }

        $ownerId = null;
        if (isset($record->user_id)) {
            $ownerId = $record->user_id;
        } elseif ($field === 'kegiatan_id') {
            $ownerId = optional($record->jadwal)->user_id;
        }

        if ($ownerId !== Auth::id()) {
            abort(403, 'Akses target tidak diizinkan.');
        }
    }
}
