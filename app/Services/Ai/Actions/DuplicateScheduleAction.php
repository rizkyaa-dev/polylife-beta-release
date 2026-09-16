<?php

namespace App\Services\Ai\Actions;

use App\Actions\Jadwal\StoreJadwalAction;
use App\Actions\Kegiatan\SaveKegiatanAction;
use App\Models\Jadwal;
use App\Models\User;
use App\Services\Ai\ProposalFreshnessGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

final class DuplicateScheduleAction implements AiWriteAction
{
    public function __construct(
        private readonly StoreJadwalAction $storeSchedule,
        private readonly SaveKegiatanAction $saveActivity,
        private readonly ProposalFreshnessGuard $freshness
    ) {}

    public function toolName(): string
    {
        return 'duplicate_schedule';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'source_jadwal_id' => ['required', 'integer'],
            'expected_updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'title' => ['nullable', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'copy_activities' => ['required', 'boolean'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $source = Jadwal::query()->where('user_id', $user->id)->with('kegiatans')
            ->lockForUpdate()->findOrFail($validated['source_jadwal_id']);
        $this->freshness->assertUnchanged($source, $validated['expected_updated_at'], 'Jadwal sumber');

        $schedule = ($this->storeSchedule)((int) $user->id, [
            'jenis' => $source->jenis,
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'semester' => $source->semester,
            'catatan_tambahan' => $source->catatan_tambahan,
            'title' => $validated['title'] ?? $source->title,
            'location' => $source->location,
            'start_time' => $source->start_time,
            'end_time' => $source->end_time,
            'is_completed' => false,
        ], $source->matkulIds()->all(), false, []);

        if ($validated['copy_activities']) {
            $dayShift = Carbon::parse($source->tanggal_mulai)->diffInDays(Carbon::parse($validated['tanggal_mulai']), false);
            foreach ($source->kegiatans as $activity) {
                ($this->saveActivity)(null, (int) $user->id, [
                    'jadwal_id' => $schedule->id,
                    'nama_kegiatan' => $activity->nama_kegiatan,
                    'lokasi' => $activity->lokasi,
                    'tanggal_deadline' => Carbon::parse($activity->tanggal_deadline)->addDays($dayShift)->toDateString(),
                    'waktu' => $activity->waktu,
                    'status' => 'belum_dimulai',
                ]);
            }
        }

        return $schedule;
    }
}
