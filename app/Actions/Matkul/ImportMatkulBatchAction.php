<?php

namespace App\Actions\Matkul;

use App\Models\Matkul;
use Illuminate\Support\Str;
use Throwable;

class ImportMatkulBatchAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function __invoke(int $userId, array $validated): array
    {
        $entries = $this->parseBatchRows((string) $validated['raw_data']);
        if ($entries === []) {
            return [
                'created' => 0,
                'updated' => 0,
                'failed' => [],
                'total' => 0,
                'readable' => false,
            ];
        }

        $defaults = [
            'semester' => (int) $validated['default_semester'],
            'dosen' => trim((string) $validated['default_dosen']),
            'warna_label' => $this->normalizeColor((string) $validated['default_warna_label']),
            'catatan_prefix' => trim((string) ($validated['catatan_prefix'] ?? '')),
        ];

        return array_merge(
            ['readable' => true],
            $this->storeBatchEntries($userId, $entries, $defaults)
        );
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
        $chunk = trim((string) $chunk);
        if ($chunk === '') {
            return null;
        }

        $lines = preg_split("/\n+/", $chunk);
        $lines = array_values(array_filter(array_map(fn ($line) => trim($line), $lines), fn ($line) => $line !== ''));
        $firstLine = array_shift($lines);
        if (! $firstLine || ! preg_match('/^\d+/', $firstLine)) {
            return null;
        }

        $parts = preg_split('/\t+|\s{2,}/', trim($firstLine), 7);
        if (! $parts || count($parts) < 6) {
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

        if (! str_starts_with($color, '#')) {
            $color = '#' . $color;
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

    /**
     * @return array<string, string>|null
     */
    private function parseScheduleLine(?string $line): ?array
    {
        $line = trim((string) $line);
        if ($line === '') {
            return null;
        }

        $pattern = '/^(?P<hari>[A-Za-z]+),\s*(?P<mulai>\d{1,2}:\d{2})\s*s\.d\s*(?P<selesai>\d{1,2}:\d{2})\s*@\s*(?P<ruangan>.+)$/u';
        if (! preg_match($pattern, $line, $matches)) {
            return null;
        }

        $hari = $this->normalizeDay($matches['hari']);
        $mulai = $this->normalizeTime($matches['mulai']);
        $selesai = $this->normalizeTime($matches['selesai']);
        if (! $hari || ! $mulai || ! $selesai) {
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
        if (! empty($row['keterangan'])) {
            $notes[] = $row['keterangan'];
        }
        if (! empty($scheduleLines)) {
            $notes[] = "Jadwal lengkap:\n" . implode("\n", $scheduleLines);
        }

        $catatan = trim(implode("\n\n", array_filter($notes)));
        if ($catatan === '') {
            $catatan = 'Diimpor otomatis pada ' . now()->format('d M Y H:i');
        }

        return $catatan;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function storeBatchEntries(int $userId, array $entries, array $defaults): array
    {
        $created = 0;
        $updated = 0;
        $failed = [];

        foreach ($entries as $entry) {
            $scheduleLines = $entry['jadwal_lines'] ?? [];
            $scheduleCollection = collect($scheduleLines)
                ->map(fn ($line) => $this->parseScheduleLine($line))
                ->filter()
                ->values();

            if ($scheduleCollection->isEmpty()) {
                $failed[] = [
                    'kode' => $entry['kode'] ?? 'Tanpa kode',
                    'reason' => 'Format jadwal tidak dikenali.',
                ];
                continue;
            }

            $data = [
                'user_id' => $userId,
                'kode' => $entry['kode'],
                'nama' => $entry['nama'] ?: $entry['kode'],
                'kelas' => $this->normalizeKelasValues($entry['kelas'] ?? ''),
                'dosen' => $defaults['dosen'],
                'semester' => $defaults['semester'],
                'sks' => max(1, (int) ($entry['sks'] ?: 1)),
                'hari' => $this->formatSemicolonValues($scheduleCollection->pluck('hari'), true),
                'jam_mulai' => $this->formatSemicolonValues($scheduleCollection->pluck('jam_mulai')),
                'jam_selesai' => $this->formatSemicolonValues($scheduleCollection->pluck('jam_selesai')),
                'ruangan' => $this->formatSemicolonValues(
                    $scheduleCollection->pluck('ruangan')->map(fn ($ruangan) => Str::limit($ruangan, 120, ''))
                ),
                'warna_label' => $defaults['warna_label'],
                'catatan' => $this->composeCatatan($entry, $scheduleLines, $defaults['catatan_prefix']),
            ];

            try {
                $matkul = Matkul::query()->updateOrCreate(
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
                    'reason' => 'Gagal menyimpan: ' . $e->getMessage(),
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

    private function normalizeKelasValues(?string $value): string
    {
        $chunks = preg_split('/[;,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        if (! $chunks) {
            $chunks = ['A'];
        }

        return $this->formatSemicolonValues($chunks) ?? 'A;';
    }
}
