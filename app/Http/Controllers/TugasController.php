<?php

namespace App\Http\Controllers;

use App\Actions\Tugas\FindOwnedTugasAction;
use App\Actions\Tugas\SaveTugasAction;
use App\Http\Requests\Tugas\StoreTugasRequest;
use App\Http\Requests\Tugas\UpdateTugasRequest;
use App\Models\Tugas;
use Illuminate\Support\Facades\Auth;

class TugasController extends Controller
{
    public function __construct(
        private readonly FindOwnedTugasAction $findOwnedTugasAction,
        private readonly SaveTugasAction $saveTugasAction
    ) {
    }

    public function index()
    {
        $tugas = Tugas::query()->where('user_id', Auth::id())->latest()->get();

        return view('tugas.index', compact('tugas'));
    }

    public function create()
    {
        return view('tugas.create');
    }

    public function store(StoreTugasRequest $request)
    {
        ($this->saveTugasAction)(null, Auth::id(), $request->validated(), $request->boolean('status_selesai'));

        return redirect()->route('tugas.index')->with('success', 'Tugas berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $tugas = ($this->findOwnedTugasAction)(Auth::id(), $id);

        return view('tugas.edit', compact('tugas'));
    }

    public function update(UpdateTugasRequest $request, $id)
    {
        $tugas = ($this->findOwnedTugasAction)(Auth::id(), $id);
        ($this->saveTugasAction)($tugas, Auth::id(), $request->validated(), $request->boolean('status_selesai'));

        return redirect()->route('tugas.index')->with('success', 'Tugas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $tugas = ($this->findOwnedTugasAction)(Auth::id(), $id);
        $tugas->delete();

        return redirect()->route('tugas.index')->with('success', 'Tugas berhasil dihapus.');
    }
}
