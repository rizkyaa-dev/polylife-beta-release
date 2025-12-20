<?php

namespace App\Http\Controllers;

use App\Models\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TugasController extends Controller
{
    public function index()
    {
        $tugas = Tugas::where('user_id', Auth::id())->latest()->get();
        return view('tugas.index', compact('tugas'));
    }

    public function create()
    {
        return view('tugas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_tugas' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:255',
            'deadline' => 'required|date',
            'status_selesai' => 'boolean',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status_selesai'] = $request->has('status_selesai');

        Tugas::create($validated);

        return redirect()->route('tugas.index')->with('success', 'Tugas berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $tugas = $this->findTugasForUser($id);
        return view('tugas.edit', compact('tugas'));
    }

    public function update(Request $request, $id)
    {
        $tugas = $this->findTugasForUser($id);

        $validated = $request->validate([
            'nama_tugas' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:255',
            'deadline' => 'required|date',
            'status_selesai' => 'boolean',
        ]);

        $validated['status_selesai'] = $request->has('status_selesai');

        $tugas->update($validated);

        return redirect()->route('tugas.index')->with('success', 'Tugas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $tugas = $this->findTugasForUser($id);
        $tugas->delete();

        return redirect()->route('tugas.index')->with('success', 'Tugas berhasil dihapus.');
    }

    private function findTugasForUser($id): Tugas
    {
        return Tugas::where('user_id', Auth::id())->findOrFail($id);
    }
}
