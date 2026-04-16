<?php

namespace App\Http\Controllers;

use App\Actions\Kegiatan\SaveKegiatanAction;
use App\Http\Requests\Kegiatan\StoreKegiatanRequest;
use App\Http\Requests\Kegiatan\UpdateKegiatanRequest;
use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Services\Jadwal\UserJadwalReferenceService;
use Illuminate\Support\Facades\Auth;

class KegiatanController extends Controller
{
    public function __construct(
        private readonly SaveKegiatanAction $saveKegiatanAction,
        private readonly UserJadwalReferenceService $userJadwalReferenceService
    ) {
    }

    public function index()
    {
        $jadwals = Jadwal::query()
            ->where('user_id', Auth::id())
            ->with(['kegiatans' => function ($query) {
                $query->orderBy('waktu');
            }])
            ->orderBy('tanggal_mulai')
            ->get();

        $this->userJadwalReferenceService->forUser(Auth::id())
            ->keyBy('id')
            ->each(function ($referenceJadwal, $id) use ($jadwals) {
                $jadwal = $jadwals->firstWhere('id', $id);
                if ($jadwal) {
                    $jadwal->matkul_names = $referenceJadwal->matkul_names ?? [];
                    $jadwal->primary_matkul = $referenceJadwal->primary_matkul ?? null;
                    $jadwal->matkul_details = $referenceJadwal->matkul_details ?? collect();
                }
            });

        return view('kegiatan.index', compact('jadwals'));
    }

    public function create()
    {
        $jadwals = $this->userJadwalReferenceService->forUser(Auth::id());

        return view('kegiatan.create', compact('jadwals'));
    }

    public function store(StoreKegiatanRequest $request)
    {
        ($this->saveKegiatanAction)(null, Auth::id(), $request->validated());

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function edit(Kegiatan $kegiatan)
    {
        $this->authorizeAccess($kegiatan);
        $jadwals = $this->userJadwalReferenceService->forUser(Auth::id());

        return view('kegiatan.edit', compact('kegiatan', 'jadwals'));
    }

    public function update(UpdateKegiatanRequest $request, Kegiatan $kegiatan)
    {
        $this->authorizeAccess($kegiatan);
        ($this->saveKegiatanAction)($kegiatan, Auth::id(), $request->validated());

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(Kegiatan $kegiatan)
    {
        $this->authorizeAccess($kegiatan);
        $kegiatan->delete();

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil dihapus.');
    }

    private function authorizeAccess(Kegiatan $kegiatan): void
    {
        if ((int) $kegiatan->jadwal->user_id !== (int) Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
