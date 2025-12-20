<?php

namespace App\Support;

use App\Models\Catatan;
use App\Models\Ipk;
use App\Models\Jadwal;
use App\Models\Keuangan;
use App\Models\Kegiatan;
use App\Models\Matkul;
use App\Models\NilaiMutu;
use App\Models\Reminder;
use App\Models\Todolist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class GuestWorkspace
{
    public static function keuangan(): Collection
    {
        $rows = static::payload()['keuangan'] ?? [];

        return collect(is_array($rows) ? $rows : [])
            ->map(function ($row, $index) {
                $model = new Keuangan();
                $model->forceFill([
                    'id' => $row['id'] ?? ($index + 1),
                    'jenis' => $row['jenis'] ?? 'pemasukan',
                    'kategori' => $row['kategori'] ?? 'Lainnya',
                    'deskripsi' => $row['deskripsi'] ?? null,
                    'nominal' => (float) ($row['nominal'] ?? 0),
                    'tanggal' => $row['tanggal'] ?? Carbon::now()->toDateString(),
                ]);

                return $model;
            });
    }

    public static function matkuls(): Collection
    {
        $rows = static::payload()['matkuls'] ?? [];

        return collect(is_array($rows) ? $rows : [])
            ->map(function ($row, $index) {
                $model = new Matkul();
                $model->forceFill([
                    'id' => $row['id'] ?? (100 + $index),
                    'kode' => $row['kode'] ?? 'MK-' . ($index + 1),
                    'nama' => $row['nama'] ?? 'Mata Kuliah Demo',
                    'kelas' => $row['kelas'] ?? 'A',
                    'dosen' => $row['dosen'] ?? 'Dosen Demo',
                    'semester' => $row['semester'] ?? 1,
                    'sks' => $row['sks'] ?? 3,
                    'hari' => $row['hari'] ?? 'Senin',
                    'jam_mulai' => $row['jam_mulai'] ?? '08:00',
                    'jam_selesai' => $row['jam_selesai'] ?? '09:40',
                    'ruangan' => $row['ruangan'] ?? 'Ruang Demo',
                    'warna_label' => $row['warna_label'] ?? '#6366f1',
                    'catatan' => $row['catatan'] ?? null,
                ]);

                return $model;
            });
    }

    public static function jadwals(): Collection
    {
        $rows = static::payload()['jadwals'] ?? [];
        $matkuls = static::matkuls()->keyBy('id');

        return collect(is_array($rows) ? $rows : [])
            ->map(function ($row, $index) use ($matkuls) {
                $model = new Jadwal();
                $matkulIds = collect($row['matkul_ids'] ?? [])
                    ->map(fn ($id) => (string) $id)
                    ->filter()
                    ->values();

                $model->forceFill([
                    'id' => $row['id'] ?? (300 + $index),
                    'jenis' => $row['jenis'] ?? 'kuliah',
                    'tanggal_mulai' => Carbon::parse($row['tanggal_mulai'] ?? Carbon::now()->toDateString()),
                    'tanggal_selesai' => Carbon::parse($row['tanggal_selesai'] ?? Carbon::now()->toDateString()),
                    'semester' => $row['semester'] ?? null,
                    'catatan_tambahan' => $row['catatan_tambahan'] ?? null,
                    'matkul_id_list' => $matkulIds->isEmpty() ? null : $matkulIds->implode(';') . ';',
                ]);

                $kegiatans = collect($row['kegiatans'] ?? [])->map(function ($item, $kIndex) use ($model) {
                    $kegiatan = new Kegiatan();
                    $deadlineRaw = $item['tanggal_deadline'] ?? null;
                    $timeRaw = $item['waktu'] ?? null;
                    $kegiatan->forceFill([
                        'id' => $item['id'] ?? (500 + $kIndex),
                        'nama_kegiatan' => $item['nama_kegiatan'] ?? 'Kegiatan',
                        'lokasi' => $item['lokasi'] ?? null,
                        'waktu' => $timeRaw ? Carbon::parse($timeRaw) : null,
                        'tanggal_deadline' => $deadlineRaw ? Carbon::parse($deadlineRaw)->toDateString() : null,
                        'jadwal_id' => $model->id,
                    ]);

                    return $kegiatan;
                });

                $model->setRelation('kegiatans', $kegiatans);

                $matkulDetails = $matkulIds
                    ->map(fn ($id) => $matkuls->get((int) $id))
                    ->filter()
                    ->values();
                $model->matkul_details = $matkulDetails;
                $model->matkul_names = $matkulDetails->pluck('nama')->filter()->values()->all();
                $model->primary_matkul = $matkulDetails->first();

                return $model;
            });
    }

    public static function todolists(): Collection
    {
        $rows = static::payload()['todolist'] ?? [];

        return collect(is_array($rows) ? $rows : [])
            ->map(function ($row, $index) {
                $model = new Todolist();
                $model->forceFill([
                    'id' => $row['id'] ?? (700 + $index),
                    'nama_item' => $row['nama_item'] ?? 'Tugas demo',
                    'status' => (bool) ($row['status'] ?? false),
                    'created_at' => Carbon::parse($row['created_at'] ?? Carbon::now()->subDays(2)),
                    'updated_at' => Carbon::parse($row['updated_at'] ?? Carbon::now()->subDay()),
                ]);

                $reminders = collect($row['reminders'] ?? [])->map(function ($rem, $rIndex) use ($model) {
                    $reminder = new Reminder();
                    $reminder->forceFill([
                        'id' => $rem['id'] ?? (800 + $rIndex),
                        'todolist_id' => $model->id,
                        'waktu_reminder' => Carbon::parse($rem['waktu_reminder'] ?? Carbon::now()->addDay()),
                        'aktif' => (bool) ($rem['aktif'] ?? false),
                    ]);

                    return $reminder;
                });

                $model->setRelation('reminders', $reminders);

                return $model;
            });
    }

    public static function catatans(): Collection
    {
        $rows = static::payload()['catatan'] ?? [];

        return collect(is_array($rows) ? $rows : [])
            ->map(function ($row, $index) {
                $model = new Catatan();
                $model->forceFill([
                    'id' => $row['id'] ?? (900 + $index),
                    'judul' => $row['judul'] ?? 'Catatan demo',
                    'isi' => $row['isi'] ?? 'Ubah isi catatan demo di storage/app/guest/workspace.json.',
                    'tanggal' => $row['tanggal'] ?? Carbon::now()->toDateString(),
                    'status_sampah' => (bool) ($row['status_sampah'] ?? false),
                ]);

                return $model;
            });
    }

    public static function ipks(): Collection
    {
        $rows = static::payload()['ipk'] ?? [];

        return collect(is_array($rows) ? $rows : [])
            ->map(function ($row, $index) {
                $model = new Ipk();
                $model->forceFill([
                    'id' => $row['id'] ?? (1000 + $index),
                    'semester' => $row['semester'] ?? ($index + 1),
                    'academic_year' => $row['academic_year'] ?? null,
                    'ips_actual' => isset($row['ips_actual']) ? (float) $row['ips_actual'] : null,
                    'ips_target' => isset($row['ips_target']) ? (float) $row['ips_target'] : null,
                    'ipk_running' => isset($row['ipk_running']) ? (float) $row['ipk_running'] : null,
                    'ipk_target' => isset($row['ipk_target']) ? (float) $row['ipk_target'] : null,
                    'remarks' => $row['remarks'] ?? null,
                ]);

                return $model;
            })
            ->sortBy('semester')
            ->values();
    }

    public static function nilaiMutus(): Collection
    {
        $rows = static::payload()['nilai_mutu'] ?? [];

        return collect(is_array($rows) ? $rows : [])
            ->map(function ($row, $index) {
                $model = new NilaiMutu();
                $model->forceFill([
                    'id' => $row['id'] ?? (1200 + $index),
                    'kampus' => $row['kampus'] ?? 'Kampus Demo',
                    'program_studi' => $row['program_studi'] ?? 'Teknik Informatika',
                    'kurikulum' => $row['kurikulum'] ?? (string) Carbon::now()->year,
                    'grades_plus_minus' => $row['grades_plus_minus'] ?? [],
                    'grades_ab' => $row['grades_ab'] ?? [],
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'notes' => $row['notes'] ?? null,
                ]);

                return $model;
            });
    }

    protected static function payload(): array
    {
        $path = storage_path('app/guest/workspace.json');
        $defaults = static::defaults();

        if (! File::exists($path)) {
            return $defaults;
        }

        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        return [
            'keuangan' => is_array($decoded['keuangan'] ?? null) ? $decoded['keuangan'] : $defaults['keuangan'],
            'matkuls' => is_array($decoded['matkuls'] ?? null) ? $decoded['matkuls'] : $defaults['matkuls'],
            'jadwals' => is_array($decoded['jadwals'] ?? null) ? $decoded['jadwals'] : $defaults['jadwals'],
            'todolist' => is_array($decoded['todolist'] ?? null) ? $decoded['todolist'] : $defaults['todolist'],
            'catatan' => is_array($decoded['catatan'] ?? null) ? $decoded['catatan'] : $defaults['catatan'],
            'ipk' => is_array($decoded['ipk'] ?? null) ? $decoded['ipk'] : $defaults['ipk'],
            'nilai_mutu' => is_array($decoded['nilai_mutu'] ?? null) ? $decoded['nilai_mutu'] : $defaults['nilai_mutu'],
        ];
    }

    protected static function defaults(): array
    {
        return [
            'keuangan' => [],
            'matkuls' => [],
            'jadwals' => [],
            'todolist' => [],
            'catatan' => [],
            'ipk' => [],
            'nilai_mutu' => [],
        ];
    }
}
