<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Matkul;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JadwalController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $selectedDate = request('tanggal')
            ? Carbon::parse(request('tanggal'))
            : Carbon::now();

        $calendarMonth = request('bulan')
            ? Carbon::parse(request('bulan') . '-01')
            : $selectedDate->copy()->startOfMonth();

        $startCalendar = $calendarMonth->copy()->startOfMonth()->startOfWeek();
        $endCalendar = $calendarMonth->copy()->endOfMonth()->endOfWeek();

        $jadwals = Jadwal::with('kegiatans')
            ->where('user_id', $userId)
            ->whereDate('tanggal_selesai', '>=', $startCalendar->copy()->toDateString())
            ->whereDate('tanggal_mulai', '<=', $endCalendar->copy()->toDateString())
            ->orderBy('tanggal_mulai')
            ->get();

        $matkuls = Matkul::where('user_id', $userId)
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        $this->appendMatkulNames($jadwals, $userId, $matkuls);
        $jadwalsByDate = $this->deduplicateKuliahByDate(
            $this->mapJadwalsByDate($jadwals)
        );

        $calendarDays = [];
        $iterate = $startCalendar->copy();
        while ($iterate <= $endCalendar) {
            $calendarDays[] = $iterate->copy();
            $iterate->addDay();
        }

        $selectedDateKey = $selectedDate->toDateString();
        $selectedDayEvents = $this->deduplicateKuliahCollection(
            ($jadwalsByDate[$selectedDateKey] ?? collect())->sortBy('tanggal_mulai')
        );

        return view('jadwal.index', [
            'jadwals' => $jadwals,
            'calendarDays' => $calendarDays,
            'selectedDate' => $selectedDate,
            'calendarMonth' => $calendarMonth,
            'jadwalsByDate' => $jadwalsByDate,
            'selectedDayEvents' => $selectedDayEvents,
            'kegiatanDays' => $this->collectKegiatanDays($jadwals),
            'kegiatanByDate' => $this->collectKegiatanByDate($jadwals),
            'matkuls' => $matkuls,
        ]);
    }

    public function create()
    {
        $matkuls = Matkul::where('user_id', Auth::id())
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        return view('jadwal.create', compact('matkuls'));
    }

    public function store(Request $request)
    {
        [$jadwalData, $selectedMatkuls, $createMatkul, $matkulFields] = $this->validateJadwal($request);
        $jadwalData['user_id'] = Auth::id();
        $jadwalData['matkul_id_list'] = $this->buildMatkulList($selectedMatkuls, $createMatkul, $matkulFields);

        Jadwal::create($jadwalData);

        return redirect()->route('jadwal.index')->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function edit(Jadwal $jadwal)
    {
        $this->authorizeAccess($jadwal);
        $matkuls = Matkul::where('user_id', Auth::id())
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();
        $this->appendMatkulNames(collect([$jadwal]), Auth::id());

        return view('jadwal.edit', compact('jadwal', 'matkuls'));
    }

    public function update(Request $request, Jadwal $jadwal)
    {
        $this->authorizeAccess($jadwal);

        [$jadwalData, $selectedMatkuls, $createMatkul, $matkulFields] = $this->validateJadwal($request);
        $jadwalData['matkul_id_list'] = $this->buildMatkulList($selectedMatkuls, $createMatkul, $matkulFields);

        $jadwal->update($jadwalData);

        return redirect()->route('jadwal.index')->with('success', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(Jadwal $jadwal)
    {
        $this->authorizeAccess($jadwal);
        $jadwal->delete();

        return redirect()->route('jadwal.index')->with('success', 'Jadwal berhasil dihapus.');
    }

    public function confirmDestroy(Jadwal $jadwal)
    {
        $this->authorizeAccess($jadwal);
        $this->appendMatkulNames(collect([$jadwal]), Auth::id());

        return view('jadwal.confirm-delete', compact('jadwal'));
    }

    private function authorizeAccess(Jadwal $jadwal)
    {
        if ($jadwal->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }

    private function validateJadwal(Request $request): array
    {
        $jenisOptions = ['kuliah', 'libur', 'uts', 'uas', 'lomba', 'lainnya'];

        $validated = $request->validate([
            'jenis' => ['required', Rule::in($jenisOptions)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'catatan_tambahan' => ['nullable', 'string', 'max:500'],
            'matkul_ids' => ['nullable', 'array'],
            'matkul_ids.*' => [
                'nullable',
                Rule::exists('matkuls', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'matkul_create' => ['nullable', 'boolean'],
            'matkul_nama' => ['required_if:matkul_create,1', 'nullable', 'string', 'max:120'],
            'matkul_kode' => ['nullable', 'string', 'max:20'],
            'matkul_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
        ]);

        $selectedMatkuls = $validated['matkul_ids'] ?? [];
        $createMatkul = (bool) ($validated['matkul_create'] ?? false);
        $matkulFields = collect($validated)->only(['matkul_kode', 'matkul_nama', 'matkul_semester'])->toArray();

        $jadwalData = collect($validated)->except([
            'matkul_ids',
            'matkul_create',
            'matkul_kode',
            'matkul_nama',
            'matkul_semester',
        ])->toArray();

        return [$jadwalData, $selectedMatkuls, $createMatkul, $matkulFields];
    }

    private function buildMatkulList(array $selectedIds, bool $createMatkul, array $matkulData): ?string
    {
        $ids = collect($selectedIds);

        if ($createMatkul && !empty($matkulData['matkul_nama'])) {
            $matkul = Matkul::create([
                'user_id' => Auth::id(),
                'kode' => $matkulData['matkul_kode'] ?? null,
                'nama' => $matkulData['matkul_nama'],
                'semester' => isset($matkulData['matkul_semester'])
                    ? (int) $matkulData['matkul_semester']
                    : null,
            ]);

            $ids->push($matkul->id);
        }

        $normalized = $ids
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return null;
        }

        return $normalized->implode(';') . ';';
    }

    private function appendMatkulNames($jadwals, $userId, ?Collection $matkuls = null): Collection
    {
        $matkulCollection = $matkuls ?: Matkul::where('user_id', $userId)->get();
        $matkulMap = $matkulCollection->keyBy('id');
        foreach ($jadwals as $jadwal) {
            $ids = $jadwal->matkulIds();
            $matkulMeta = $ids->map(fn ($id) => $matkulMap->get((int) $id))->filter();
            $jadwal->matkul_names = $matkulMeta->pluck('nama')->filter()->values()->all();
            $jadwal->primary_matkul = $matkulMeta->first();
            $jadwal->matkul_details = $matkulMeta->values();
        }

        return $matkulCollection;
    }

    private function mapJadwalsByDate($jadwals): array
    {
        $map = [];
        foreach ($jadwals as $jadwal) {
            $start = Carbon::parse($jadwal->tanggal_mulai);
            $end = Carbon::parse($jadwal->tanggal_selesai);
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                // Kuliah otomatis libur saat akhir pekan, jadi jangan tempelkan ke kalender
                if ($jadwal->jenis === 'kuliah' && $cursor->isWeekend()) {
                    $cursor->addDay();
                    continue;
                }

                $key = $cursor->toDateString();
                if (!isset($map[$key])) {
                    $map[$key] = collect();
                }
                $map[$key]->push($jadwal);
                $cursor->addDay();
            }
        }

        return $map;
    }

    private function deduplicateKuliahByDate(array $map): array
    {
        foreach ($map as $key => $collection) {
            $seen = [];
            $map[$key] = $collection->filter(function ($jadwal) use (&$seen) {
                if (($jadwal->jenis ?? null) !== 'kuliah') {
                    return true;
                }

                $signature = $this->kuliahSignature($jadwal);
                if (isset($seen[$signature])) {
                    return false;
                }

                $seen[$signature] = true;
                return true;
            })->values();
        }

        return $map;
    }

    private function kuliahSignature(Jadwal $jadwal): string
    {
        $signatures = collect();

        $matkulIds = $jadwal->matkulIds()
            ->map(fn ($id) => 'id:' . (string) $id)
            ->filter();
        if ($matkulIds->isNotEmpty()) {
            $signatures = $signatures->merge($matkulIds);
        }

        $matkulNames = collect($jadwal->matkul_names ?? [])
            ->map(fn ($name) => 'name:' . Str::lower(trim((string) $name)))
            ->filter();
        if ($matkulNames->isNotEmpty()) {
            $signatures = $signatures->merge($matkulNames);
        }

        $matkulDetails = collect($jadwal->matkul_details ?? [])
            ->map(fn ($matkul) => $this->matkulDetailSignature($matkul))
            ->filter();
        if ($matkulDetails->isNotEmpty()) {
            $signatures = $signatures->merge($matkulDetails);
        }

        if ($signatures->isEmpty()) {
            $signatures = $jadwal->matkulIds()
                ->merge($matkulDetails)
                ->merge($matkulNames)
                ->filter();
        }

        $signature = $signatures
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(';');
        if ($signature === '') {
            $signature = 'generic';
        }

        return 'kuliah|' . $signature;
    }

    private function deduplicateKuliahCollection(Collection $jadwals): Collection
    {
        $seen = [];

        return $jadwals->filter(function ($jadwal) use (&$seen) {
            if (($jadwal->jenis ?? null) !== 'kuliah') {
                return true;
            }

            $signature = $this->kuliahSignature($jadwal);
            if (isset($seen[$signature])) {
                return false;
            }

            $seen[$signature] = true;
            return true;
        })->values();
    }

    private function matkulDetailSignature($matkul): string
    {
        $name = Str::lower(trim((string) ($matkul->nama ?? '')));
        $kode = Str::lower(trim((string) ($matkul->kode ?? '')));
        $id = isset($matkul->id) ? (string) $matkul->id : '';
        $start = $this->resolveMatkulTime($matkul, 'primaryStartTime', 'jam_mulai');
        $end = $this->resolveMatkulTime($matkul, 'primaryEndTime', 'jam_selesai');

        if ($id === '' && $name === '' && $kode === '' && $start === '' && $end === '') {
            return '';
        }

        return implode('|', [
            $id !== '' ? 'id:' . $id : '',
            $name !== '' ? 'name:' . $name : '',
            $kode !== '' ? 'kode:' . $kode : '',
            $start !== '' ? 'start:' . $start : '',
            $end !== '' ? 'end:' . $end : '',
        ]);
    }

    private function resolveMatkulTime($matkul, string $method, string $property): string
    {
        if (method_exists($matkul, $method)) {
            $value = $matkul->{$method}();
        } elseif (isset($matkul->{$property})) {
            $value = $matkul->{$property};
        } else {
            $value = null;
        }

        return $value ? (string) $value : '';
    }

    private function collectKegiatanDays($jadwals): array
    {
        $kegiatanDays = [];
        foreach ($jadwals as $jadwal) {
            foreach ($jadwal->kegiatans as $kegiatan) {
                $tanggal = $kegiatan->tanggal_deadline
                    ? Carbon::parse($kegiatan->tanggal_deadline)->toDateString()
                    : optional($kegiatan->waktu)->toDateString();
                if (!$tanggal) {
                    continue;
                }
                $kegiatanDays[$tanggal] = true;
            }
        }

        return $kegiatanDays;
    }

    private function collectKegiatanByDate($jadwals): array
    {
        $map = [];
        foreach ($jadwals as $jadwal) {
            foreach ($jadwal->kegiatans as $kegiatan) {
                $tanggal = $kegiatan->tanggal_deadline
                    ? Carbon::parse($kegiatan->tanggal_deadline)->toDateString()
                    : optional($kegiatan->waktu)->toDateString();
                if (!$tanggal) {
                    continue;
                }
                if (!isset($map[$tanggal])) {
                    $map[$tanggal] = collect();
                }
                $map[$tanggal]->push($kegiatan);
            }
        }

        return $map;
    }
}
