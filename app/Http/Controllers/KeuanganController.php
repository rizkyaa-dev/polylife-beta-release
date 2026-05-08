<?php

namespace App\Http\Controllers;

use App\Actions\Keuangan\SaveKeuanganAction;
use App\Http\Requests\Keuangan\StoreKeuanganRequest;
use App\Http\Requests\Keuangan\UpdateKeuanganRequest;
use App\Models\Keuangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KeuanganController extends Controller
{
    public function __construct(
        private readonly SaveKeuanganAction $saveKeuanganAction
    ) {}

    public function index()
    {
        $userId = Auth::id();
        $summaryRow = Keuangan::query()
            ->where('user_id', $userId)
            ->selectRaw("SUM(CASE WHEN jenis = 'pemasukan' THEN nominal ELSE 0 END) as total_pemasukan")
            ->selectRaw("SUM(CASE WHEN jenis = 'pengeluaran' THEN nominal ELSE 0 END) as total_pengeluaran")
            ->first();

        $summary = [
            'total_pemasukan' => (float) ($summaryRow->total_pemasukan ?? 0),
            'total_pengeluaran' => (float) ($summaryRow->total_pengeluaran ?? 0),
        ];

        $keuangans = Keuangan::query()
            ->where('user_id', $userId)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('keuangan.index', compact('keuangans', 'summary'));
    }

    public function create(Request $request)
    {
        return view('keuangan.create', ['jenis' => $request->jenis]);
    }

    public function store(StoreKeuanganRequest $request)
    {
        ($this->saveKeuanganAction)(null, Auth::id(), $request->validated());

        return redirect()->route('keuangan.index')->with('success', 'Data keuangan berhasil ditambahkan.');
    }

    public function edit(Keuangan $keuangan)
    {
        $this->authorizeAccess($keuangan);

        return view('keuangan.edit', compact('keuangan'));
    }

    public function update(UpdateKeuanganRequest $request, Keuangan $keuangan)
    {
        $this->authorizeAccess($keuangan);
        ($this->saveKeuanganAction)($keuangan, Auth::id(), $request->validated());

        return redirect()->route('keuangan.index')->with('success', 'Data keuangan berhasil diperbarui.');
    }

    public function destroy(Keuangan $keuangan)
    {
        $this->authorizeAccess($keuangan);
        $keuangan->delete();

        return redirect()->route('keuangan.index')->with('success', 'Data keuangan berhasil dihapus.');
    }

    private function authorizeAccess(Keuangan $keuangan): void
    {
        if ((int) $keuangan->user_id !== (int) Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
