<?php

namespace App\Http\Controllers;

use App\Actions\NilaiMutu\SaveNilaiMutuAction;
use App\Http\Requests\NilaiMutu\StoreNilaiMutuRequest;
use App\Http\Requests\NilaiMutu\UpdateNilaiMutuRequest;
use App\Models\NilaiMutu;
use Illuminate\Support\Facades\Auth;

class NilaiMutuController extends Controller
{
    public function __construct(
        private readonly SaveNilaiMutuAction $saveNilaiMutuAction
    ) {
    }

    public function index()
    {
        $nilaiMutus = NilaiMutu::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('is_active')
            ->orderBy('kampus')
            ->get();

        return view('nilai-mutu.index', compact('nilaiMutus'));
    }

    public function create()
    {
        $nilaiMutu = new NilaiMutu([
            'is_active' => true,
        ]);

        return view('nilai-mutu.create', compact('nilaiMutu'));
    }

    public function store(StoreNilaiMutuRequest $request)
    {
        ($this->saveNilaiMutuAction)(null, Auth::id(), $request->normalizedPayload());

        return redirect()->route('nilai-mutu.index')->with('success', 'Profil nilai mutu berhasil disimpan.');
    }

    public function edit(NilaiMutu $nilaiMutu)
    {
        $this->authorizeAccess($nilaiMutu);

        return view('nilai-mutu.edit', compact('nilaiMutu'));
    }

    public function update(UpdateNilaiMutuRequest $request, NilaiMutu $nilaiMutu)
    {
        $this->authorizeAccess($nilaiMutu);
        ($this->saveNilaiMutuAction)($nilaiMutu, Auth::id(), $request->normalizedPayload());

        return redirect()->route('nilai-mutu.index')->with('success', 'Profil nilai mutu diperbarui.');
    }

    public function destroy(NilaiMutu $nilaiMutu)
    {
        $this->authorizeAccess($nilaiMutu);
        $nilaiMutu->delete();

        return redirect()->route('nilai-mutu.index')->with('success', 'Profil nilai mutu dihapus.');
    }

    private function authorizeAccess(NilaiMutu $nilaiMutu): void
    {
        if ((int) $nilaiMutu->user_id !== (int) Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
