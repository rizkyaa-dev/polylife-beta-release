<?php

namespace App\Http\Controllers;

use App\Models\Catatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CatatanController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $catatans = Catatan::where('user_id', $userId)
            ->where('status_sampah', false)
            ->latest('tanggal')
            ->get();

        $trashCount = Catatan::where('user_id', $userId)
            ->where('status_sampah', true)
            ->count();

        return view('catatan.index', compact('catatans', 'trashCount'));
    }

    public function trash()
    {
        $catatans = Catatan::where('user_id', Auth::id())
            ->where('status_sampah', true)
            ->latest('updated_at')
            ->get();

        return view('catatan.sampah', compact('catatans'));
    }

    public function create()
    {
        return view('catatan.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:150',
            'isi' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status_sampah'] = false;

        Catatan::create($validated);

        return redirect()->route('catatan.index')->with('success', 'Catatan berhasil ditambahkan.');
    }

    public function edit(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);
        return view('catatan.edit', compact('catatan'));
    }

    public function update(Request $request, Catatan $catatan)
    {
        $this->authorizeAccess($catatan);

        $validated = $request->validate([
            'judul' => 'required|string|max:150',
            'isi' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $catatan->update($validated);

        return redirect()->route('catatan.index')->with('success', 'Catatan berhasil diperbarui.');
    }

    public function destroy(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);

        $catatan->update(['status_sampah' => true]);

        return redirect()->route('catatan.index')->with('success', 'Catatan dipindahkan ke sampah.');
    }

    public function restore(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);

        $catatan->update(['status_sampah' => false]);

        return redirect()->route('catatan.sampah')->with('success', 'Catatan berhasil dipulihkan.');
    }

    public function forceDelete(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);

        $catatan->delete();

        return redirect()->route('catatan.sampah')->with('success', 'Catatan dihapus permanen.');
    }

    private function authorizeAccess(Catatan $catatan)
    {
        if ($catatan->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
