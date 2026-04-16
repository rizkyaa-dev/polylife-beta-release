<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreKeuanganRequest;
use App\Http\Requests\Api\UpdateKeuanganRequest;
use App\Http\Resources\Api\KeuanganResource;
use App\Models\Keuangan;
use App\Queries\Keuangan\AvailableMonthOptionsQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class KeuanganController extends Controller
{
    public function __construct(
        private readonly AvailableMonthOptionsQuery $availableMonthOptionsQuery
    ) {
    }

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
            'data' => KeuanganResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'selected_month' => $selectedMonth->format('Y-m'),
                'month_options' => $this->availableMonthOptionsQuery->forUser($user->id, $selectedMonth, $today),
                'summary' => $this->buildSummary($items),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function store(StoreKeuanganRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
            'data' => (new KeuanganResource($keuangan))->resolve(),
        ], 201);
    }

    public function show(Request $request, int $keuangan): JsonResponse
    {
        $item = $this->findOwnedOrFail($request, $keuangan);

        return response()->json([
            'data' => (new KeuanganResource($item))->resolve(),
        ]);
    }

    public function update(UpdateKeuanganRequest $request, int $keuangan): JsonResponse
    {
        $item = $this->findOwnedOrFail($request, $keuangan);
        $validated = $request->validated();

        $item->update([
            'jenis' => $validated['jenis'],
            'kategori' => trim((string) $validated['kategori']),
            'deskripsi' => isset($validated['deskripsi']) ? (string) $validated['deskripsi'] : null,
            'nominal' => $validated['nominal'],
            'tanggal' => $validated['tanggal'],
        ]);

        return response()->json([
            'message' => 'Data keuangan berhasil diperbarui.',
            'data' => (new KeuanganResource($item->fresh()))->resolve(),
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
}
