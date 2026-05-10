<?php

namespace App\Http\Controllers;

use App\Actions\Catatan\BulkForceDeleteCatatanAction;
use App\Actions\Catatan\BulkRestoreCatatanAction;
use App\Actions\Catatan\BulkTrashCatatanAction;
use App\Actions\Catatan\DeleteCatatanAction;
use App\Actions\Catatan\RestoreCatatanAction;
use App\Actions\Catatan\SaveCatatanAction;
use App\Actions\Catatan\TrashCatatanAction;
use App\Http\Requests\Catatan\BulkProcessTrashCatatanRequest;
use App\Http\Requests\Catatan\BulkTrashCatatanRequest;
use App\Http\Requests\Catatan\StoreCatatanRequest;
use App\Http\Requests\Catatan\UpdateCatatanRequest;
use App\Models\Catatan;
use App\Services\Catatan\CatatanSearchIndexer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CatatanController extends Controller
{
    private const INDEX_PER_PAGE = 12;

    private const MANAGE_PER_PAGE = 20;

    private const TRASH_PER_PAGE = 20;

    public function __construct(
        private readonly SaveCatatanAction $saveCatatanAction,
        private readonly TrashCatatanAction $trashCatatanAction,
        private readonly RestoreCatatanAction $restoreCatatanAction,
        private readonly DeleteCatatanAction $deleteCatatanAction,
        private readonly BulkTrashCatatanAction $bulkTrashCatatanAction,
        private readonly BulkRestoreCatatanAction $bulkRestoreCatatanAction,
        private readonly BulkForceDeleteCatatanAction $bulkForceDeleteCatatanAction,
        private readonly CatatanSearchIndexer $catatanSearchIndexer
    ) {}

    public function index()
    {
        $userId = Auth::id();

        $catatans = Catatan::query()
            ->selectWebSummary()
            ->where('user_id', $userId)
            ->where('status_sampah', false)
            ->latest('tanggal')
            ->latest('id')
            ->paginate(self::INDEX_PER_PAGE);

        $trashCount = Catatan::query()
            ->where('user_id', $userId)
            ->where('status_sampah', true)
            ->count();

        return view('catatan.index', compact('catatans', 'trashCount'));
    }

    public function manage(Request $request)
    {
        $userId = Auth::id();
        $search = trim((string) $request->string('q'));
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));
        $sort = (string) $request->string('sort', 'latest');

        $query = Catatan::query()
            ->selectWebSummary()
            ->where('user_id', $userId)
            ->where('status_sampah', false);

        if ($search !== '') {
            $tokenHashes = $this->catatanSearchIndexer->hashesForSearch($search);

            $query->where(function ($builder) use ($search, $tokenHashes) {
                $builder->where('judul', 'like', '%'.$search.'%');

                if ($tokenHashes !== []) {
                    $builder->orWhere(function ($tokenQuery) use ($tokenHashes) {
                        foreach ($tokenHashes as $tokenHash) {
                            $tokenQuery->whereExists(function ($exists) use ($tokenHash) {
                                $exists->selectRaw('1')
                                    ->from('catatan_search_tokens')
                                    ->whereColumn('catatan_search_tokens.catatan_id', 'catatans.id')
                                    ->whereColumn('catatan_search_tokens.user_id', 'catatans.user_id')
                                    ->where('catatan_search_tokens.token_hash', $tokenHash);
                            });
                        }
                    });
                }
            });
        }

        if ($dateFrom !== '') {
            $query->whereDate('tanggal', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('tanggal', '<=', $dateTo);
        }

        match ($sort) {
            'oldest' => $query->oldest('tanggal')->oldest('id'),
            'title_asc' => $query->orderBy('judul')->latest('tanggal'),
            'title_desc' => $query->orderByDesc('judul')->latest('tanggal'),
            default => $query->latest('tanggal')->latest('id'),
        };

        $catatans = $query
            ->paginate(self::MANAGE_PER_PAGE)
            ->withQueryString();
        $trashCount = Catatan::query()
            ->where('user_id', $userId)
            ->where('status_sampah', true)
            ->count();

        return view('catatan.manage', [
            'catatans' => $catatans,
            'trashCount' => $trashCount,
            'filters' => [
                'q' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
            ],
        ]);
    }

    public function trash()
    {
        $catatans = Catatan::query()
            ->selectWebSummary()
            ->where('user_id', Auth::id())
            ->where('status_sampah', true)
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::TRASH_PER_PAGE);

        return view('catatan.sampah', compact('catatans'));
    }

    public function create()
    {
        return view('catatan.create');
    }

    public function store(StoreCatatanRequest $request)
    {
        ($this->saveCatatanAction)(null, Auth::id(), $request->validated());

        return redirect()->route('catatan.index')->with('success', 'Catatan berhasil ditambahkan.');
    }

    public function show(Request $request, Catatan $catatan): JsonResponse
    {
        $this->authorizeAccess($catatan);

        return response()->json([
            'data' => [
                'id' => (int) $catatan->id,
                'judul' => (string) $catatan->judul,
                'isi' => (string) ($catatan->isi ?? ''),
                'preview_isi' => $catatan->previewForDisplay(),
                'show_preview' => (bool) $catatan->show_preview,
                'tanggal' => optional($catatan->tanggal)->toDateString() ?? (string) $catatan->tanggal,
                'status_sampah' => (bool) $catatan->status_sampah,
                'created_at' => optional($catatan->created_at)->toIso8601String(),
                'updated_at' => optional($catatan->updated_at)->toIso8601String(),
            ],
        ]);
    }

    public function edit(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);

        return view('catatan.edit', compact('catatan'));
    }

    public function update(UpdateCatatanRequest $request, Catatan $catatan)
    {
        $this->authorizeAccess($catatan);
        ($this->saveCatatanAction)($catatan, Auth::id(), $request->validated());

        return redirect()->route('catatan.index')->with('success', 'Catatan berhasil diperbarui.');
    }

    public function destroy(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);
        ($this->trashCatatanAction)($catatan);

        return redirect()->route('catatan.index')->with('success', 'Catatan dipindahkan ke sampah.');
    }

    public function bulkTrash(BulkTrashCatatanRequest $request)
    {
        $validated = $request->validated();

        $catatans = Catatan::query()
            ->where('user_id', Auth::id())
            ->where('status_sampah', false)
            ->whereIn('id', $validated['catatan_ids'])
            ->get();

        $affected = ($this->bulkTrashCatatanAction)($catatans);

        $routeParams = $request->only(['q', 'date_from', 'date_to', 'sort']);
        if ($request->filled('page')) {
            $routeParams['page'] = $request->input('page');
        }

        if ($affected === 0) {
            return redirect()
                ->route('catatan.manage', $routeParams)
                ->with('error', 'Tidak ada catatan yang bisa dipindahkan ke sampah.');
        }

        return redirect()
            ->route('catatan.manage', $routeParams)
            ->with('success', $affected.' catatan dipindahkan ke sampah.');
    }

    public function restore(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);
        ($this->restoreCatatanAction)($catatan);

        return redirect()->route('catatan.sampah')->with('success', 'Catatan berhasil dipulihkan.');
    }

    public function bulkRestore(BulkProcessTrashCatatanRequest $request)
    {
        $validated = $request->validated();

        $catatans = Catatan::query()
            ->where('user_id', Auth::id())
            ->where('status_sampah', true)
            ->whereIn('id', $validated['catatan_ids'])
            ->get();

        $affected = ($this->bulkRestoreCatatanAction)($catatans);

        $routeParams = [];
        if ($request->filled('page')) {
            $routeParams['page'] = $request->integer('page');
        }

        if ($affected === 0) {
            return redirect()
                ->route('catatan.sampah', $routeParams)
                ->with('error', 'Tidak ada catatan yang bisa dipulihkan.');
        }

        return redirect()
            ->route('catatan.sampah', $routeParams)
            ->with('success', $affected.' catatan berhasil dipulihkan.');
    }

    public function forceDelete(Catatan $catatan)
    {
        $this->authorizeAccess($catatan);
        ($this->deleteCatatanAction)($catatan);

        return redirect()->route('catatan.sampah')->with('success', 'Catatan dihapus permanen.');
    }

    public function bulkForceDelete(BulkProcessTrashCatatanRequest $request)
    {
        $validated = $request->validated();

        $catatans = Catatan::query()
            ->where('user_id', Auth::id())
            ->where('status_sampah', true)
            ->whereIn('id', $validated['catatan_ids'])
            ->get();

        $affected = ($this->bulkForceDeleteCatatanAction)($catatans);

        $routeParams = [];
        if ($request->filled('page')) {
            $routeParams['page'] = $request->integer('page');
        }

        if ($affected === 0) {
            return redirect()
                ->route('catatan.sampah', $routeParams)
                ->with('error', 'Tidak ada catatan yang bisa dihapus permanen.');
        }

        return redirect()
            ->route('catatan.sampah', $routeParams)
            ->with('success', $affected.' catatan dihapus permanen.');
    }

    private function authorizeAccess(Catatan $catatan): void
    {
        if ((int) $catatan->user_id !== (int) Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
