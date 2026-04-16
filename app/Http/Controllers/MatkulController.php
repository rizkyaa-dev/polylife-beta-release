<?php

namespace App\Http\Controllers;

use App\Actions\Matkul\ImportMatkulBatchAction;
use App\Actions\Matkul\StoreMatkulAction;
use App\Actions\Matkul\UpdateMatkulAction;
use App\Http\Requests\Matkul\BatchImportMatkulRequest;
use App\Http\Requests\Matkul\StoreMatkulRequest;
use App\Http\Requests\Matkul\UpdateMatkulRequest;
use App\Models\Matkul;
use Illuminate\Support\Facades\Auth;

class MatkulController extends Controller
{
    public function __construct(
        private readonly StoreMatkulAction $storeMatkulAction,
        private readonly UpdateMatkulAction $updateMatkulAction,
        private readonly ImportMatkulBatchAction $importMatkulBatchAction
    ) {
    }

    public function index()
    {
        $matkuls = Matkul::where('user_id', Auth::id())
            ->orderBy('semester')
            ->orderBy('nama')
            ->get();

        return view('matkul.index', compact('matkuls'));
    }

    public function create()
    {
        return view('matkul.create');
    }

    public function batch()
    {
        return view('matkul.batch');
    }

    public function batchImport(BatchImportMatkulRequest $request)
    {
        $result = ($this->importMatkulBatchAction)(Auth::id(), $request->validated());

        if (! ($result['readable'] ?? false)) {
            return back()
                ->withErrors(['raw_data' => 'Data tidak terbaca. Pastikan format tabel sudah sesuai.'])
                ->withInput();
        }

        return redirect()
            ->route('matkul.batch')
            ->with('success', "Batch selesai: {$result['created']} matkul baru, {$result['updated']} diperbarui.")
            ->with('batch_result', $result);
    }

    public function store(StoreMatkulRequest $request)
    {
        ($this->storeMatkulAction)(Auth::id(), $request->validated());

        return redirect()->route('matkul.index')->with('success', 'Matkul berhasil ditambahkan.');
    }

    public function edit(Matkul $matkul)
    {
        $this->authorizeAccess($matkul);
        return view('matkul.edit', compact('matkul'));
    }

    public function update(UpdateMatkulRequest $request, Matkul $matkul)
    {
        $this->authorizeAccess($matkul);
        ($this->updateMatkulAction)($matkul, $request->validated());

        return redirect()->route('matkul.index')->with('success', 'Matkul berhasil diperbarui.');
    }

    public function destroy(Matkul $matkul)
    {
        $this->authorizeAccess($matkul);
        $matkul->delete();

        return redirect()->route('matkul.index')->with('success', 'Matkul berhasil dihapus.');
    }

    private function authorizeAccess(Matkul $matkul): void
    {
        if ($matkul->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
