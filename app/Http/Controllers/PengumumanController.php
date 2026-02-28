<?php

namespace App\Http\Controllers;

use App\Models\AffiliationBroadcast;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        $broadcastsQuery = AffiliationBroadcast::query()
            ->with(['creator:id,name,email', 'targets'])
            ->visibleToUser($user);

        if ($search !== '') {
            $broadcastsQuery->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        $broadcasts = $broadcastsQuery
            ->latest('published_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

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
        $user = $request->user();

        $isVisible = AffiliationBroadcast::query()
            ->whereKey($broadcast->id)
            ->visibleToUser($user)
            ->exists();

        if (! $isVisible) {
            abort(404);
        }

        $broadcast->load(['creator:id,name,email', 'targets']);

        $relatedBroadcasts = AffiliationBroadcast::query()
            ->visibleToUser($user)
            ->where('id', '!=', $broadcast->id)
            ->latest('published_at')
            ->latest('id')
            ->limit(4)
            ->get(['id', 'title', 'published_at']);

        return view('pengumuman.show', [
            'broadcast' => $broadcast,
            'relatedBroadcasts' => $relatedBroadcasts,
        ]);
    }
}
