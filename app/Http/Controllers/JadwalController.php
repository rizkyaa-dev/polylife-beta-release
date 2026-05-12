<?php

namespace App\Http\Controllers;

use App\Actions\Jadwal\StoreJadwalAction;
use App\Actions\Jadwal\UpdateJadwalAction;
use App\Http\Requests\Jadwal\StoreJadwalRequest;
use App\Http\Requests\Jadwal\UpdateJadwalRequest;
use App\Models\Jadwal;
use App\Models\Matkul;
use App\Queries\Jadwal\JadwalCalendarQuery;
use App\Services\Jadwal\KuliahScheduleService;
use App\ViewModels\JadwalIndexViewModel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class JadwalController extends Controller
{
    public function __construct(
        private readonly JadwalCalendarQuery $jadwalCalendarQuery,
        private readonly StoreJadwalAction $storeJadwalAction,
        private readonly UpdateJadwalAction $updateJadwalAction,
        private readonly KuliahScheduleService $kuliahScheduleService
    ) {}

    public function index()
    {
        $userId = Auth::id();
        $selectedDate = request('tanggal')
            ? Carbon::parse(request('tanggal'))
            : Carbon::now();

        $calendarMonth = request('bulan')
            ? Carbon::parse(request('bulan').'-01')
            : $selectedDate->copy()->startOfMonth();

        $viewModel = JadwalIndexViewModel::fromPayload(
            $this->jadwalCalendarQuery->forUser($userId, $selectedDate, $calendarMonth)
        );

        return view('jadwal.index', $viewModel->toArray());
    }

    public function create()
    {
        $matkuls = Matkul::where('user_id', Auth::id())
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        return view('jadwal.create', compact('matkuls'));
    }

    public function manage(Request $request)
    {
        $userId = Auth::id();
        $today = Carbon::today();
        $search = trim((string) $request->string('q'));
        $jenis = trim((string) $request->string('jenis'));
        $status = trim((string) $request->string('status'));
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));
        $sort = trim((string) $request->string('sort'));

        $summaryQuery = Jadwal::query()->where('user_id', $userId);

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'today' => (clone $summaryQuery)
                ->whereDate('tanggal_mulai', '<=', $today)
                ->whereDate('tanggal_selesai', '>=', $today)
                ->when(Auth::user()->isOffDay($today), fn ($query) => $query->where('jenis', '!=', 'kuliah'))
                ->count(),
            'kuliah' => (clone $summaryQuery)->where('jenis', 'kuliah')->count(),
            'completed' => (clone $summaryQuery)
                ->where(function ($query) use ($today) {
                    $query->where('is_completed', true)
                        ->orWhereDate('tanggal_selesai', '<', $today);
                })
                ->count(),
        ];

        $jadwalsQuery = Jadwal::query()
            ->where('user_id', $userId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('catatan_tambahan', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%');
                });
            })
            ->when($jenis !== '', fn ($query) => $query->where('jenis', $jenis))
            ->when($status !== '', function ($query) use ($status, $today) {
                match ($status) {
                    'running' => $query
                        ->where('is_completed', false)
                        ->whereDate('tanggal_mulai', '<=', $today)
                        ->whereDate('tanggal_selesai', '>=', $today),
                    'upcoming' => $query
                        ->where('is_completed', false)
                        ->whereDate('tanggal_mulai', '>', $today),
                    'completed' => $query->where(function ($innerQuery) use ($today) {
                        $innerQuery->where('is_completed', true)
                            ->orWhereDate('tanggal_selesai', '<', $today);
                    }),
                    default => null,
                };
            })
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('tanggal_selesai', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('tanggal_mulai', '<=', $dateTo));

        match ($sort) {
            'oldest' => $jadwalsQuery->orderBy('tanggal_mulai'),
            'updated' => $jadwalsQuery->orderByDesc('updated_at'),
            'ending_soon' => $jadwalsQuery->orderBy('tanggal_selesai')->orderBy('tanggal_mulai'),
            default => $jadwalsQuery->orderByDesc('tanggal_mulai')->orderByDesc('id'),
        };

        $jadwals = $jadwalsQuery->paginate(12)->withQueryString();
        $this->kuliahScheduleService->appendMatkulDetailsForUser($jadwals->getCollection(), $userId);

        return view('jadwal.manage', [
            'jadwals' => $jadwals,
            'summary' => $summary,
            'filters' => [
                'q' => $search,
                'jenis' => $jenis,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort !== '' ? $sort : 'latest',
            ],
        ]);
    }

    public function store(StoreJadwalRequest $request)
    {
        ($this->storeJadwalAction)(
            Auth::id(),
            $request->jadwalData(),
            $request->selectedMatkulIds(),
            $request->shouldCreateMatkul(),
            $request->matkulFields()
        );

        return redirect()->route('jadwal.index')->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function edit(Jadwal $jadwal)
    {
        $this->authorizeAccess($jadwal);
        $matkuls = Matkul::where('user_id', Auth::id())
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();
        $this->kuliahScheduleService->appendMatkulDetails(collect([$jadwal]), $matkuls);

        return view('jadwal.edit', compact('jadwal', 'matkuls'));
    }

    public function update(UpdateJadwalRequest $request, Jadwal $jadwal)
    {
        $this->authorizeAccess($jadwal);

        ($this->updateJadwalAction)(
            $jadwal,
            $request->jadwalData(),
            $request->selectedMatkulIds(),
            $request->shouldCreateMatkul(),
            $request->matkulFields()
        );

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
        $this->kuliahScheduleService->appendMatkulDetailsForUser(collect([$jadwal]), Auth::id());

        return view('jadwal.confirm-delete', compact('jadwal'));
    }

    private function authorizeAccess(Jadwal $jadwal)
    {
        if ($jadwal->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
