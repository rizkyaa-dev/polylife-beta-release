<?php

namespace App\Http\Controllers;

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

        $request->session()->put('pengumuman_last_seen_at', now()->toDateTimeString());

        return view('pengumuman.index', [
            'broadcasts' => $broadcasts,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function show(Request $request, AffiliationBroadcast $broadcast)
    {
        $item = $this->visiblePengumumanQuery->findVisibleForUser($request->user(), (int) $broadcast->id);

        if (! $item) {
            abort(404);
        }

        return view('pengumuman.show', [
            'broadcast' => $item,
            'relatedBroadcasts' => $this->visiblePengumumanQuery->relatedForUser($request->user(), (int) $item->id),
        ]);
    }
}
