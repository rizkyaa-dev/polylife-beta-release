<?php

namespace App\Http\Controllers;

use App\Models\Keuangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KeuanganController extends Controller
{
    public function index()
    {
        $keuangans = Keuangan::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('keuangan.index', compact('keuangans'));
    }

    public function create(Request $request)
    {
        return view('keuangan.create', ['jenis' => $request->jenis]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jenis' => 'required|in:pemasukan,pengeluaran',
            'kategori' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'nominal' => 'required|numeric|min:0',
            'tanggal' => 'required|date',
        ]);

        Keuangan::create([
            'user_id' => auth()->id(),
            'jenis' => $request->jenis,
            'kategori' => $request->kategori,
            'deskripsi' => $request->deskripsi,
            'nominal' => $request->nominal,
            'tanggal' => $request->tanggal,
        ]);

        return redirect()->route('keuangan.index')->with('success', 'Data keuangan berhasil ditambahkan.');
    }

    public function edit(Keuangan $keuangan)
    {
        $this->authorizeAccess($keuangan);
        return view('keuangan.edit', compact('keuangan'));
    }

    public function update(Request $request, Keuangan $keuangan)
    {
        $this->authorizeAccess($keuangan);

        $validated = $request->validate([
            'jenis' => 'required|in:pemasukan,pengeluaran',
            'kategori' => 'required|string|max:255',
            'nominal' => 'required|numeric|min:0',
            'deskripsi' => 'nullable|string|max:255',
            'tanggal' => 'required|date',
        ]);

        $keuangan->update($validated);

        return redirect()->route('keuangan.index')->with('success', 'Data keuangan berhasil diperbarui.');
    }

    public function destroy(Keuangan $keuangan)
    {
        $this->authorizeAccess($keuangan);
        $keuangan->delete();

        return redirect()->route('keuangan.index')->with('success', 'Data keuangan berhasil dihapus.');
    }

    private function authorizeAccess(Keuangan $keuangan)
    {
        if ($keuangan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
    }

}
