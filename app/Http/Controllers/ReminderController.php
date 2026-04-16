<?php

namespace App\Http\Controllers;

use App\Actions\Reminder\StoreReminderAction;
use App\Actions\Reminder\UpdateReminderAction;
use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Models\Reminder;
use App\Services\Reminder\ReminderPayloadService;
use App\Services\Reminder\ReminderReferenceDataService;
use Illuminate\Support\Facades\Auth;
use App\ViewModels\ReminderFormViewModel;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ReminderReferenceDataService $reminderReferenceDataService,
        private readonly ReminderPayloadService $reminderPayloadService,
        private readonly StoreReminderAction $storeReminderAction,
        private readonly UpdateReminderAction $updateReminderAction
    ) {
    }

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
        $payload = $this->reminderReferenceDataService->forUser($userId);
        $payload['reminder'] = null;
        $payload['selectedTarget'] = 'todolist';

        return view('reminder.create', ReminderFormViewModel::fromPayload($payload)->toArray());
    }

    public function store(StoreReminderRequest $request)
    {
        ($this->storeReminderAction)(Auth::id(), $request->validated(), $request->all());

        return redirect()->route('reminder.index')->with('success', 'Reminder berhasil ditambahkan.');
    }

    public function edit(Reminder $reminder)
    {
        $this->reminderPayloadService->assertOwnership($reminder, Auth::id());
        $userId = Auth::id();
        $payload = $this->reminderReferenceDataService->forUser($userId);
        $payload['reminder'] = $reminder;
        $payload['selectedTarget'] = $this->reminderPayloadService->resolveReminderTarget($reminder);

        return view('reminder.edit', ReminderFormViewModel::fromPayload($payload)->toArray());
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder)
    {
        ($this->updateReminderAction)($reminder, Auth::id(), $request->validated(), $request->all());

        return redirect()->route('reminder.index')->with('success', 'Reminder berhasil diperbarui.');
    }

    public function destroy(Reminder $reminder)
    {
        $this->reminderPayloadService->assertOwnership($reminder, Auth::id());
        $reminder->delete();

        return redirect()->route('reminder.index')->with('success', 'Reminder berhasil dihapus.');
    }
}
