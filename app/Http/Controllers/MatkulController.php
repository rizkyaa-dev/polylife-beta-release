<?php

namespace App\Http\Controllers;

use App\Models\Matkul;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MatkulController extends Controller
{
    public function index()
    {
        $matkuls = Matkul::where('user_id', Auth::id())
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        return view('matkul.index', compact('matkuls'));
    }

    public function create()
    {
        return view('matkul.create');
    }

    public function batch()
    {
        return view('matkul.batch');
    }

    public function batchImport(Request $request)
    {
        $validated = $request->validate([
            'raw_data' => 'required|string|min:10',
            'default_semester' => 'required|integer|min:1|max:14',
            'default_dosen' => 'required|string|max:120',
            'default_warna_label' => 'required|string|regex:/^#?[0-9a-fA-F]{3,6}$/',
            'catatan_prefix' => 'nullable|string|max:255',
        ]);

        $entries = $this->parseBatchRows($validated['raw_data']);
        if (!count($entries)) {
            return back()
                ->withErrors(['raw_data' => 'Data tidak terbaca. Pastikan format tabel sudah sesuai.'])
                ->withInput();
        }

        $defaults = [
            'semester' => (int) $validated['default_semester'],
            'dosen' => trim($validated['default_dosen']),
            'warna_label' => $this->normalizeColor($validated['default_warna_label']),
            'catatan_prefix' => trim((string) ($validated['catatan_prefix'] ?? '')),
        ];

        $result = $this->storeBatchEntries($entries, $defaults);

        return redirect()
            ->route('matkul.batch')
            ->with('success', "Batch selesai: {$result['created']} matkul baru, {$result['updated']} diperbarui.")
            ->with('batch_result', $result);
    }

    public function store(Request $request)
    {
        $data = $this->validateMatkul($request);
        $data['user_id'] = Auth::id();
        $data = $this->prepareMatkulPayload($data);

        Matkul::create($data);

        return redirect()->route('matkul.index')->with('success', 'Matkul berhasil ditambahkan.');
    }

    public function edit(Matkul $matkul)
    {
        $this->authorizeAccess($matkul);
        return view('matkul.edit', compact('matkul'));
    }

    public function update(Request $request, Matkul $matkul)
    {
        $this->authorizeAccess($matkul);
        $data = $this->validateMatkul($request, $matkul->id);
        $data = $this->prepareMatkulPayload($data);
        $matkul->update($data);

        return redirect()->route('matkul.index')->with('success', 'Matkul berhasil diperbarui.');
    }

    public function destroy(Matkul $matkul)
    {
        $this->authorizeAccess($matkul);
        $matkul->delete();

        return redirect()->route('matkul.index')->with('success', 'Matkul berhasil dihapus.');
    }

    private function validateMatkul(Request $request, ?int $matkulId = null): array
    {
        return $request->validate([
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::unique('matkuls', 'kode')
                    ->where(fn($query) => $query->where('user_id', Auth::id()))
                    ->ignore($matkulId),
            ],
            'nama' => 'required|string|max:150',
            'kelas' => 'required|string|max:255',
            'dosen' => 'required|string|max:120',
            'semester' => 'required|integer|min:1|max:14',
            'sks' => 'required|integer|min:1|max:6',
            'hari' => 'required|string|max:255',
            'jam_mulai' => 'required|string|max:255',
            'jam_selesai' => 'required|string|max:255',
            'ruangan' => 'required|string|max:255',
            'warna_label' => 'required|string|max:20',
            'catatan' => 'required|string',
        ]);
    }

    private function authorizeAccess(Matkul $matkul): void
    {
        if ($matkul->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseBatchRows(string $raw): array
    {
        $normalized = preg_replace("/\r\n|\r/", "\n", trim($raw));
        if ($normalized === '') {
            return [];
        }

        $lines = preg_split("/\n+/", $normalized);
        if ($lines && isset($lines[0]) && str_contains(Str::lower($lines[0]), 'kode')) {
            array_shift($lines);
            $normalized = trim(implode("\n", $lines));
        }

        if ($normalized === '') {
            return [];
        }

        $chunks = preg_split('/\n(?=\s*\d+)/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $rows = [];
        foreach ($chunks as $chunk) {
            $parsed = $this->parseBatchChunk($chunk);
            if ($parsed) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseBatchChunk(?string $chunk): ?array
    {
        $chunk = trim($chunk ?? '');
        if ($chunk === '') {
            return null;
        }

        $lines = preg_split("/\n+/", $chunk);
        $lines = array_values(array_filter(array_map(fn ($line) => trim($line), $lines), fn ($line) => $line !== ''));
        $firstLine = array_shift($lines);
        if (!$firstLine || !preg_match('/^\d+/', $firstLine)) {
            return null;
        }

        $parts = preg_split('/\t+|\s{2,}/', trim($firstLine), 7);
        if (!$parts || count($parts) < 6) {
            return null;
        }

        $kode = trim($parts[1] ?? '');
        $nama = trim($parts[2] ?? '');
        if ($kode === '' || $nama === '') {
            return null;
        }

        $jadwalLines = array_filter(
            array_map('trim', array_merge([trim($parts[5] ?? '')], $lines)),
            fn ($line) => $line !== ''
        );

        return [
            'kode' => $kode,
            'nama' => $nama,
            'kelas' => trim($parts[3] ?? ''),
            'sks' => trim($parts[4] ?? ''),
            'jadwal_lines' => $jadwalLines,
            'keterangan' => trim($parts[6] ?? ''),
        ];
    }

    private function normalizeColor(string $value): string
    {
        $color = trim($value);
        if ($color === '') {
            return '#2563eb';
        }

        if (!str_starts_with($color, '#')) {
            $color = '#'.$color;
        }

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color) ? strtolower($color) : '#2563eb';
    }

    private function normalizeDay(string $value): ?string
    {
        $map = [
            'senin' => 'Senin',
            'selasa' => 'Selasa',
            'rabu' => 'Rabu',
            'kamis' => 'Kamis',
            'jumat' => 'Jumat',
            'jum\'at' => 'Jumat',
            'sabtu' => 'Sabtu',
            'minggu' => 'Minggu',
        ];

        $key = strtolower(trim($value));
        return $map[$key] ?? null;
    }

    private function normalizeTime(string $time): ?string
    {
        $time = trim($time);
        foreach (['H:i', 'G:i'] as $format) {
            $dt = \DateTime::createFromFormat($format, $time);
            if ($dt !== false) {
                return $dt->format('H:i');
            }
        }
        return null;
    }

    private function parseScheduleLine(?string $line): ?array
    {
        $line = trim((string) $line);
        if ($line === '') {
            return null;
        }

        $pattern = '/^(?P<hari>[A-Za-z]+),\s*(?P<mulai>\d{1,2}:\d{2})\s*s\.d\s*(?P<selesai>\d{1,2}:\d{2})\s*@\s*(?P<ruangan>.+)$/u';
        if (!preg_match($pattern, $line, $matches)) {
            return null;
        }

        $hari = $this->normalizeDay($matches['hari']);
        $mulai = $this->normalizeTime($matches['mulai']);
        $selesai = $this->normalizeTime($matches['selesai']);
        if (!$hari || !$mulai || !$selesai) {
            return null;
        }

        return [
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'ruangan' => trim($matches['ruangan']),
        ];
    }

    private function composeCatatan(array $row, array $scheduleLines, string $prefix = ''): string
    {
        $notes = [];
        if ($prefix !== '') {
            $notes[] = $prefix;
        }
        if (!empty($row['keterangan'])) {
            $notes[] = $row['keterangan'];
        }
        if (!empty($scheduleLines)) {
            $notes[] = "Jadwal lengkap:\n".implode("\n", $scheduleLines);
        }

        $catatan = trim(implode("\n\n", array_filter($notes)));
        if ($catatan === '') {
            $catatan = 'Diimpor otomatis pada '.now()->format('d M Y H:i');
        }

        return $catatan;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function storeBatchEntries(array $entries, array $defaults): array
    {
        $created = 0;
        $updated = 0;
        $failed = [];
        $userId = Auth::id();

        foreach ($entries as $entry) {
            $scheduleLines = $entry['jadwal_lines'] ?? [];
            $scheduleCollection = $this->parseScheduleLines($scheduleLines);
            if ($scheduleCollection->isEmpty()) {
                $failed[] = [
                    'kode' => $entry['kode'] ?? 'Tanpa kode',
                    'reason' => 'Format jadwal tidak dikenali.',
                ];
                continue;
            }

            $hariString = $this->formatSemicolonValues($scheduleCollection->pluck('hari'), true);
            $jamMulaiString = $this->formatSemicolonValues($scheduleCollection->pluck('jam_mulai'));
            $jamSelesaiString = $this->formatSemicolonValues($scheduleCollection->pluck('jam_selesai'));
            $ruanganString = $this->formatSemicolonValues(
                $scheduleCollection
                    ->pluck('ruangan')
                    ->map(fn ($ruangan) => Str::limit($ruangan, 120, ''))
            );

            $data = [
                'user_id' => $userId,
                'kode' => $entry['kode'],
                'nama' => $entry['nama'] ?: $entry['kode'],
                'kelas' => $this->normalizeKelasValues($entry['kelas'] ?? ''),
                'dosen' => $defaults['dosen'],
                'semester' => $defaults['semester'],
                'sks' => max(1, (int) ($entry['sks'] ?: 1)),
                'hari' => $hariString,
                'jam_mulai' => $jamMulaiString,
                'jam_selesai' => $jamSelesaiString,
                'ruangan' => $ruanganString,
                'warna_label' => $defaults['warna_label'],
                'catatan' => $this->composeCatatan($entry, $scheduleLines, $defaults['catatan_prefix']),
            ];

            try {
                $matkul = Matkul::updateOrCreate(
                    ['user_id' => $userId, 'kode' => $data['kode']],
                    $data
                );
                if ($matkul->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (Throwable $e) {
                report($e);
                $failed[] = [
                    'kode' => $entry['kode'] ?? 'Tanpa kode',
                    'reason' => 'Gagal menyimpan: '.$e->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($entries),
        ];
    }

    private function parseScheduleLines(array $scheduleLines)
    {
        return collect($scheduleLines)
            ->map(fn ($line) => $this->parseScheduleLine($line))
            ->filter()
            ->values();
    }

    private function prepareMatkulPayload(array $data): array
    {
        $data['kelas'] = $this->normalizeKelasValues($data['kelas'] ?? '');
        $data['hari'] = $this->buildSemicolonFromInput($data['hari'] ?? null, true) ?? $data['hari'];
        $data['jam_mulai'] = $this->buildSemicolonFromInput($data['jam_mulai'] ?? null) ?? $data['jam_mulai'];
        $data['jam_selesai'] = $this->buildSemicolonFromInput($data['jam_selesai'] ?? null) ?? $data['jam_selesai'];
        $data['ruangan'] = $this->buildSemicolonFromInput($data['ruangan'] ?? null) ?? $data['ruangan'];

        return $data;
    }

    private function formatSemicolonValues(iterable $values, bool $lowercase = false): ?string
    {
        $normalized = collect($values)
            ->map(function ($value) use ($lowercase) {
                $value = trim((string) $value);
                if ($lowercase) {
                    $value = Str::lower($value);
                }
                return $value;
            })
            ->filter(fn ($value) => $value !== '')
            ->values();

        if ($normalized->isEmpty()) {
            return null;
        }

        return $normalized->implode(';') . ';';
    }

    private function buildSemicolonFromInput($value, bool $lowercase = false): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (is_iterable($value)) {
            $values = $value;
        } else {
            $values = preg_split('/[;,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        return $this->formatSemicolonValues($values, $lowercase);
    }

    private function normalizeKelasValues(?string $value): string
    {
        $chunks = preg_split('/[;,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!$chunks) {
            $chunks = ['A'];
        }

        return $this->formatSemicolonValues($chunks) ?? 'A;';
    }
}
