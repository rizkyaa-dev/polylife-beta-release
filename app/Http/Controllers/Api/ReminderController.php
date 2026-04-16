<?php

namespace App\Http\Controllers\Api;

use App\Actions\Reminder\StoreReminderAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReminderItemResource;
use App\Http\Resources\Api\ReminderPreviewResource;
use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Models\Reminder;
use App\Queries\Dashboard\UpcomingRemindersQuery;
use App\Services\Dashboard\DashboardReminderFormatter;
use App\Services\Reminder\ReminderPayloadService;
use App\Services\Reminder\ReminderReferenceDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReminderController extends Controller
{
    public function __construct(
        private readonly UpcomingRemindersQuery $upcomingRemindersQuery,
        private readonly DashboardReminderFormatter $dashboardReminderFormatter,
        private readonly ReminderPayloadService $reminderPayloadService,
        private readonly ReminderReferenceDataService $reminderReferenceDataService,
        private readonly StoreReminderAction $storeReminderAction,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $items = Reminder::query()
            ->with(['todolist', 'tugas', 'jadwal', 'kegiatan'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('waktu_reminder')
            ->get();

        return response()->json([
            'data' => ReminderItemResource::collection($items)->resolve(),
            'meta' => [
                'total' => $items->count(),
                'active_count' => $items->where('aktif', true)->count(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $payload = $this->reminderReferenceDataService->forUser($request->user()->id);

        return response()->json([
            'data' => [
                'targets' => [
                    [
                        'key' => 'todolist',
                        'label' => 'To-Do',
                        'helper' => 'Kirim pengingat dari tugas dalam daftar kegiatan sehari-hari.',
                        'options' => $payload['todolists']->map(fn ($item) => [
                            'id' => (int) $item->id,
                            'label' => (string) $item->nama_item,
                        ])->values(),
                    ],
                    [
                        'key' => 'tugas',
                        'label' => 'Tugas Kuliah',
                        'helper' => 'Terkoneksi langsung dengan daftar tugas perkuliahan.',
                        'options' => $payload['tugasList']->map(fn ($item) => [
                            'id' => (int) $item->id,
                            'label' => trim((string) $item->nama_tugas.($item->deadline ? ' • '.Carbon::parse($item->deadline)->translatedFormat('d M') : '')),
                        ])->values(),
                    ],
                    [
                        'key' => 'jadwal',
                        'label' => 'Agenda Kuliah',
                        'helper' => 'Ingatkan agenda penting dari kalender jadwal.',
                        'options' => $payload['jadwals']->map(fn ($item) => [
                            'id' => (int) $item->id,
                            'label' => trim((string) ($item->catatan_tambahan ?: ucfirst((string) ($item->jenis ?? 'Agenda'))).($item->tanggal_mulai ? ' • '.Carbon::parse($item->tanggal_mulai)->translatedFormat('d M') : '')),
                        ])->values(),
                    ],
                    [
                        'key' => 'kegiatan',
                        'label' => 'Kegiatan Detail',
                        'helper' => 'Pengingat untuk kegiatan turunan dari sebuah jadwal.',
                        'options' => $payload['kegiatans']->map(fn ($item) => [
                            'id' => (int) $item->id,
                            'label' => trim((string) $item->nama_kegiatan.($item->tanggal_deadline ? ' • '.Carbon::parse($item->tanggal_deadline)->translatedFormat('d M H:i') : '')),
                        ])->values(),
                    ],
                ],
            ],
        ]);
    }

    public function next(Request $request): JsonResponse
    {
        $timezone = $this->dashboardTimezone();
        $now = Carbon::now($timezone);
        $reminders = $this->upcomingRemindersQuery->forUser($request->user()->id, $now, 1);
        $nextReminder = $this->dashboardReminderFormatter
            ->prepare($reminders, $now, $timezone)
            ->first();

        return response()->json([
            'data' => $nextReminder
                ? (new ReminderPreviewResource($nextReminder))->resolve()
                : null,
        ]);
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $reminder = ($this->storeReminderAction)(
            $request->user()->id,
            $request->validated(),
            $request->all()
        );

        $reminder->load(['todolist', 'tugas', 'jadwal', 'kegiatan']);

        return response()->json([
            'message' => 'Reminder berhasil ditambahkan.',
            'data' => (new ReminderItemResource($reminder))->resolve(),
        ], 201);
    }

    public function destroy(Request $request, Reminder $reminder): JsonResponse
    {
        $this->reminderPayloadService->assertOwnership($reminder, $request->user()->id);
        $reminder->delete();

        return response()->json([
            'message' => 'Reminder berhasil dihapus.',
        ]);
    }

    private function dashboardTimezone(): string
    {
        return config('app.dashboard_timezone', env('APP_DASHBOARD_TIMEZONE', 'Asia/Jakarta'));
    }
}
