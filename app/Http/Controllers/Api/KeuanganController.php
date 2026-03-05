<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keuangan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class KeuanganController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'));
        $selectedMonth = $this->resolveMonthSelection($request->query('bulan'), $today);
        $startMonth = $selectedMonth->copy()->startOfMonth();
        $endMonth = $selectedMonth->copy()->endOfMonth();
        $perPage = $this->resolvePerPage($request->query('per_page'));

        $paginator = Keuangan::query()
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items());

        return response()->json([
            'data' => $items->map(fn (Keuangan $keuangan) => $this->keuanganPayload($keuangan))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'selected_month' => $selectedMonth->format('Y-m'),
                'month_options' => $this->buildMonthOptions($user->id, $selectedMonth, $today),
                'summary' => $this->buildSummary($items),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'kategori' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal' => ['required', 'date'],
        ]);

        $keuangan = Keuangan::query()->create([
            'user_id' => $request->user()->id,
            'jenis' => $validated['jenis'],
            'kategori' => trim((string) $validated['kategori']),
            'deskripsi' => isset($validated['deskripsi']) ? (string) $validated['deskripsi'] : null,
            'nominal' => $validated['nominal'],
            'tanggal' => $validated['tanggal'],
        ]);

        return response()->json([
            'message' => 'Data keuangan berhasil ditambahkan.',
            'data' => $this->keuanganPayload($keuangan),
        ], 201);
    }

    public function show(Request $request, int $keuangan): JsonResponse
    {
        $item = $this->findOwnedOrFail($request, $keuangan);

        return response()->json([
            'data' => $this->keuanganPayload($item),
        ]);
    }

    public function update(Request $request, int $keuangan): JsonResponse
    {
        $item = $this->findOwnedOrFail($request, $keuangan);

        $validated = $request->validate([
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'kategori' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal' => ['required', 'date'],
        ]);

        $item->update([
            'jenis' => $validated['jenis'],
            'kategori' => trim((string) $validated['kategori']),
            'deskripsi' => isset($validated['deskripsi']) ? (string) $validated['deskripsi'] : null,
            'nominal' => $validated['nominal'],
            'tanggal' => $validated['tanggal'],
        ]);

        return response()->json([
            'message' => 'Data keuangan berhasil diperbarui.',
            'data' => $this->keuanganPayload($item->fresh()),
        ]);
    }

    public function destroy(Request $request, int $keuangan): JsonResponse
    {
        $item = $this->findOwnedOrFail($request, $keuangan);
        $item->delete();

        return response()->json([
            'message' => 'Data keuangan berhasil dihapus.',
        ]);
    }

    private function findOwnedOrFail(Request $request, int $id): Keuangan
    {
        $item = Keuangan::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $item) {
            throw (new ModelNotFoundException())->setModel(Keuangan::class, [$id]);
        }

        return $item;
    }

    private function resolvePerPage(mixed $rawPerPage): int
    {
        $perPage = (int) $rawPerPage;
        if ($perPage <= 0) {
            return 50;
        }

        return min($perPage, 100);
    }

    private function resolveMonthSelection(mixed $monthParam, Carbon $fallback): Carbon
    {
        $value = trim((string) ($monthParam ?? ''));
        if ($value !== '' && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $value, config('app.timezone'))->startOfMonth();
            } catch (\Throwable $e) {
                // fallback below
            }
        }

        return $fallback->copy()->startOfMonth();
    }

    private function buildMonthOptions(int $userId, Carbon $selectedMonth, Carbon $defaultMonth): array
    {
        $options = Keuangan::query()
            ->where('user_id', $userId)
            ->selectRaw('DATE_FORMAT(tanggal, "%Y-%m-01") as periode')
            ->distinct()
            ->orderByDesc('periode')
            ->limit(12)
            ->get()
            ->map(function ($row) {
                $date = Carbon::parse($row->periode);

                return [
                    'value' => $date->format('Y-m'),
                    'label' => $date->translatedFormat('F Y'),
                ];
            });

        if ($options->isEmpty()) {
            $options->push([
                'value' => $defaultMonth->format('Y-m'),
                'label' => $defaultMonth->translatedFormat('F Y'),
            ]);
        }

        if (! $options->contains(fn (array $option) => $option['value'] === $selectedMonth->format('Y-m'))) {
            $options->push([
                'value' => $selectedMonth->format('Y-m'),
                'label' => $selectedMonth->translatedFormat('F Y'),
            ]);
        }

        return $options
            ->unique('value')
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function buildSummary(Collection $items): array
    {
        $totalPemasukan = (float) $items->where('jenis', 'pemasukan')->sum('nominal');
        $totalPengeluaran = (float) $items->where('jenis', 'pengeluaran')->sum('nominal');

        return [
            'total_pemasukan' => $totalPemasukan,
            'total_pengeluaran' => $totalPengeluaran,
            'saldo' => $totalPemasukan - $totalPengeluaran,
        ];
    }

    private function keuanganPayload(Keuangan $keuangan): array
    {
        return [
            'id' => (int) $keuangan->id,
            'jenis' => (string) $keuangan->jenis,
            'kategori' => (string) ($keuangan->kategori ?? ''),
            'deskripsi' => $keuangan->deskripsi,
            'nominal' => (float) $keuangan->nominal,
            'tanggal' => (string) $keuangan->tanggal,
            'created_at' => optional($keuangan->created_at)->toIso8601String(),
            'updated_at' => optional($keuangan->updated_at)->toIso8601String(),
        ];
    }
}
