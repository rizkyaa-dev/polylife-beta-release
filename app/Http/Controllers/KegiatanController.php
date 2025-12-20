<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Jadwal;
use App\Models\Matkul;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KegiatanController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $jadwals = Jadwal::where('user_id', $userId)
            ->with(['kegiatans' => function ($query) {
                $query->orderBy('waktu');
            }])
            ->orderBy('tanggal_mulai')
            ->get();
        $this->appendMatkulNames($jadwals, $userId);
        return view('kegiatan.index', compact('jadwals'));
    }

    public function create()
    {
        $userId = Auth::id();
        $jadwals = Jadwal::where('user_id', $userId)
            ->orderBy('tanggal_mulai')
            ->get();
        $this->appendMatkulNames($jadwals, $userId);
        return view('kegiatan.create', compact('jadwals'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|exists:jadwals,id',
            'nama_kegiatan' => 'required|string|max:100',
            'lokasi' => 'nullable|string|max:100',
            'tanggal_deadline' => 'required|date',
            'waktu' => 'required|date_format:H:i',
            'status' => 'required|string|max:50',
        ]);

        $jadwal = Jadwal::findOrFail($validated['jadwal_id']);
        if ($jadwal->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }

        Kegiatan::create($validated);

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function edit(Kegiatan $kegiatan)
    {
        $this->authorizeAccess($kegiatan);
        $userId = Auth::id();
        $jadwals = Jadwal::where('user_id', $userId)
            ->orderBy('tanggal_mulai')
            ->get();
        $this->appendMatkulNames($jadwals, $userId);
        return view('kegiatan.edit', compact('kegiatan', 'jadwals'));
    }

    public function update(Request $request, Kegiatan $kegiatan)
    {
        $this->authorizeAccess($kegiatan);

        $validated = $request->validate([
            'jadwal_id' => 'required|exists:jadwals,id',
            'nama_kegiatan' => 'required|string|max:100',
            'lokasi' => 'nullable|string|max:100',
            'tanggal_deadline' => 'required|date',
            'waktu' => 'required|date_format:H:i',
            'status' => 'required|string|max:50',
        ]);

        $kegiatan->update($validated);

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(Kegiatan $kegiatan)
    {
        $this->authorizeAccess($kegiatan);
        $kegiatan->delete();

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil dihapus.');
    }

    private function authorizeAccess(Kegiatan $kegiatan)
    {
        if ($kegiatan->jadwal->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }

    private function appendMatkulNames($jadwals, $userId): void
    {
        $matkulMap = Matkul::where('user_id', $userId)->get()->keyBy('id');
        foreach ($jadwals as $jadwal) {
            $ids = $jadwal->matkulIds();
            $matkulMeta = $ids->map(fn ($id) => $matkulMap->get((int) $id))->filter();
            $jadwal->matkul_names = $matkulMeta->pluck('nama')->filter()->values()->all();
            $jadwal->primary_matkul = $matkulMeta->first();
        }
    }
}
