<?php

namespace App\Http\Controllers;

use App\Actions\Broadcast\MarkPengumumanReadAction;
use App\Models\AffiliationBroadcast;
use App\Queries\Broadcast\VisiblePengumumanQuery;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function __construct(
        private readonly VisiblePengumumanQuery $visiblePengumumanQuery
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $broadcasts = $this->visiblePengumumanQuery->paginateForUser($request->user(), $search, 12);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('pengumuman.partials.feed-items', [
                    'broadcasts' => $broadcasts,
                ])->render(),
                'next_page_url' => $broadcasts->nextPageUrl(),
            ]);
        }

        return view('pengumuman.index', [
            'broadcasts' => $broadcasts,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function show(
        Request $request,
        AffiliationBroadcast $broadcast,
        MarkPengumumanReadAction $markPengumumanRead
    )
    {
        $item = $this->visiblePengumumanQuery->findVisibleForUser($request->user(), (int) $broadcast->id);

        if (! $item) {
            abort(404);
        }

        $markPengumumanRead($request->user(), [(int) $item->id]);

        return view('pengumuman.show', [
            'broadcast' => $item,
            'relatedBroadcasts' => $this->visiblePengumumanQuery->relatedForUser($request->user(), (int) $item->id),
        ]);
    }

    public function markRead(Request $request, MarkPengumumanReadAction $markPengumumanRead)
    {
        $validated = $request->validate([
            'broadcast_ids' => ['required', 'array', 'max:50'],
            'broadcast_ids.*' => ['integer', 'min:1'],
        ]);

        return response()->json($markPengumumanRead(
            $request->user(),
            $validated['broadcast_ids']
        ));
    }
}
