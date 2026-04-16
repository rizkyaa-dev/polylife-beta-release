<?php

namespace App\Http\Resources\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JadwalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $startDate = optional($this->tanggal_mulai)->toDateString() ?? (string) $this->tanggal_mulai;
        $endDate = optional($this->tanggal_selesai)->toDateString() ?? (string) $this->tanggal_selesai;
        $matkulNames = collect($this->matkul_names ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $startTime = trim((string) ($this->start_time ?? ''));
        if ($startTime === '') {
            $startTime = '08:00:00';
        }

        $endTime = trim((string) ($this->end_time ?? ''));
        if ($endTime === '') {
            $endTime = '09:00:00';
        }

        $startAt = Carbon::parse($startDate . ' ' . $startTime);
        $endAt = Carbon::parse($endDate . ' ' . $endTime);
        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $startAt->copy()->addHour();
        }

        $title = trim((string) ($this->title ?? ''));
        if ($title === '') {
            $title = $this->defaultTitleFromJenis($this->jenis);
        }

        return [
            'id' => (int) $this->id,
            'title' => $title,
            'type' => $this->jenisToType($this->jenis),
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $endAt->toIso8601String(),
            'location' => (string) ($this->location ?? ''),
            'notes' => (string) ($this->catatan_tambahan ?? ''),
            'completed' => (bool) ($this->is_completed ?? false),
            'source_jenis' => (string) ($this->jenis ?? ''),
            'matkul_names' => $matkulNames->all(),
            'matkul_summary' => $matkulNames->implode(', '),
            'primary_matkul' => $this->serializePrimaryMatkul($this->primary_matkul ?? null),
            'matkul_previews' => collect($this->matkul_details ?? [])
                ->map(fn ($matkul) => $this->serializeMatkulPreview($matkul))
                ->filter()
                ->values()
                ->all(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function jenisToType(?string $jenis): string
    {
        $value = strtolower(trim((string) $jenis));

        return match ($value) {
            'kuliah' => 'kuliah',
            'tugas', 'deadline' => 'tugas',
            'ujian', 'uts', 'uas' => 'ujian',
            'rapat', 'meeting', 'organisasi' => 'rapat',
            default => 'personal',
        };
    }

    private function defaultTitleFromJenis(?string $jenis): string
    {
        return match ($this->jenisToType($jenis)) {
            'kuliah' => 'Agenda Kuliah',
            'tugas' => 'Agenda Tugas',
            'ujian' => 'Agenda Ujian',
            'rapat' => 'Agenda Rapat',
            default => 'Agenda Personal',
        };
    }

    private function serializePrimaryMatkul(mixed $matkul): ?array
    {
        return $this->serializeMatkulPreview($matkul);
    }

    private function serializeMatkulPreview(mixed $matkul): ?array
    {
        if (! $matkul) {
            return null;
        }

        $nama = trim((string) ($matkul->nama ?? ''));
        if ($nama === '') {
            return null;
        }

        return [
            'id' => isset($matkul->id) ? (int) $matkul->id : null,
            'nama' => $nama,
            'kelas' => $this->resolveMatkulValue($matkul, 'primaryClass', 'kelas'),
            'ruangan' => $this->resolveMatkulValue($matkul, 'primaryRoom', 'ruangan'),
            'time_label' => $this->buildMatkulTimeLabel($matkul),
            'warna_label' => trim((string) ($matkul->warna_label ?? '#4F46E5')),
            'schedule_days' => method_exists($matkul, 'scheduleDays')
                ? $matkul->scheduleDays()
                    ->map(fn ($day) => trim((string) $day))
                    ->filter()
                    ->values()
                    ->all()
                : [],
            'schedule_entries' => method_exists($matkul, 'scheduleEntries')
                ? collect($matkul->scheduleEntries())
                    ->map(function ($entry) {
                        $hari = trim((string) ($entry['hari'] ?? ''));
                        $jamMulai = trim((string) ($entry['jam_mulai'] ?? ''));
                        $jamSelesai = trim((string) ($entry['jam_selesai'] ?? ''));
                        $ruangan = trim((string) ($entry['ruangan'] ?? ''));
                        $kelas = trim((string) ($entry['kelas'] ?? ''));

                        if ($hari === '' && $jamMulai === '' && $jamSelesai === '' && $ruangan === '' && $kelas === '') {
                            return null;
                        }

                        return [
                            'hari' => $hari !== '' ? $hari : null,
                            'jam_mulai' => $jamMulai !== '' ? $jamMulai : null,
                            'jam_selesai' => $jamSelesai !== '' ? $jamSelesai : null,
                            'ruangan' => $ruangan !== '' ? $ruangan : null,
                            'kelas' => $kelas !== '' ? $kelas : null,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all()
                : [],
        ];
    }

    private function resolveMatkulValue(mixed $matkul, string $method, string $property): ?string
    {
        if (method_exists($matkul, $method)) {
            $value = $matkul->{$method}();
        } else {
            $value = $matkul->{$property} ?? null;
        }

        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function buildMatkulTimeLabel(mixed $matkul): ?string
    {
        $start = $this->resolveMatkulValue($matkul, 'primaryStartTime', 'jam_mulai');
        $end = $this->resolveMatkulValue($matkul, 'primaryEndTime', 'jam_selesai');

        if ($start && $end) {
            return $start . ' - ' . $end;
        }

        return $start ?: $end;
    }
}
