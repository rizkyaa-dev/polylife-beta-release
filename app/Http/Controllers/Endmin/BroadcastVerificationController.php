<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Models\AffiliationBroadcast;
use App\Services\BroadcastImageService;
use App\Support\Endmin\AuditLogger;
use Illuminate\Http\Request;

class BroadcastVerificationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $targetModeFilter = trim((string) $request->query('target_mode', ''));

        $broadcastsQuery = AffiliationBroadcast::query()
            ->with([
                'creator:id,name,email,is_admin,role',
                'targets:id,broadcast_id,affiliation_name',
            ]);

        if ($search !== '') {
            $broadcastsQuery->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('targets', function ($targetQuery) use ($search): void {
                        $targetQuery->where('affiliation_name', 'like', '%'.$search.'%');
                    });
            });
        }

        if (in_array($statusFilter, [
            AffiliationBroadcast::STATUS_DRAFT,
            AffiliationBroadcast::STATUS_PUBLISHED,
            AffiliationBroadcast::STATUS_ARCHIVED,
        ], true)) {
            $broadcastsQuery->where('status', $statusFilter);
        }

        if (in_array($targetModeFilter, [
            AffiliationBroadcast::TARGET_MODE_AFFILIATION,
            AffiliationBroadcast::TARGET_MODE_GLOBAL,
        ], true)) {
            $broadcastsQuery->where('target_mode', $targetModeFilter);
        }

        $broadcasts = $broadcastsQuery
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('endmin.broadcasts.index', [
            'broadcasts' => $broadcasts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'target_mode' => $targetModeFilter,
            ],
            'stats' => [
                'total' => AffiliationBroadcast::query()->count(),
                'published' => AffiliationBroadcast::query()->where('status', AffiliationBroadcast::STATUS_PUBLISHED)->count(),
                'archived' => AffiliationBroadcast::query()->where('status', AffiliationBroadcast::STATUS_ARCHIVED)->count(),
                'draft' => AffiliationBroadcast::query()->where('status', AffiliationBroadcast::STATUS_DRAFT)->count(),
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function show(AffiliationBroadcast $broadcast)
    {
        $broadcast->load(['creator:id,name,email,is_admin,role', 'targets']);
        $pushLogs = $broadcast->pushLogs()
            ->with('user:id,name,email')
            ->latest()
            ->paginate(20);

        return view('endmin.broadcasts.show', [
            'broadcast' => $broadcast,
            'pushLogs' => $pushLogs,
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function archive(Request $request, AffiliationBroadcast $broadcast)
    {
        $actor = $request->user();
        $beforeStatus = $broadcast->status;

        if (! $broadcast->isArchived()) {
            $broadcast->status = AffiliationBroadcast::STATUS_ARCHIVED;
            $broadcast->save();
        }

        AuditLogger::log(
            actor: $actor,
            module: 'broadcasts',
            action: 'archive',
            targetUser: $broadcast->creator,
            before: [
                'broadcast_id' => $broadcast->id,
                'status' => $beforeStatus,
            ],
            after: [
                'broadcast_id' => $broadcast->id,
                'status' => $broadcast->status,
            ],
            context: [
                'title' => $broadcast->title,
                'created_by' => $broadcast->created_by,
            ]
        );

        return redirect()
            ->route('endmin.broadcast-verifications.index')
            ->with('success', 'Broadcast berhasil diarsipkan.');
    }

    public function unarchive(Request $request, AffiliationBroadcast $broadcast)
    {
        $actor = $request->user();
        $beforeStatus = $broadcast->status;

        if ($broadcast->isArchived()) {
            $broadcast->status = $broadcast->published_at
                ? AffiliationBroadcast::STATUS_PUBLISHED
                : AffiliationBroadcast::STATUS_DRAFT;
            $broadcast->save();
        }

        AuditLogger::log(
            actor: $actor,
            module: 'broadcasts',
            action: 'unarchive',
            targetUser: $broadcast->creator,
            before: [
                'broadcast_id' => $broadcast->id,
                'status' => $beforeStatus,
            ],
            after: [
                'broadcast_id' => $broadcast->id,
                'status' => $broadcast->status,
            ],
            context: [
                'title' => $broadcast->title,
                'created_by' => $broadcast->created_by,
            ]
        );

        return redirect()
            ->route('endmin.broadcast-verifications.index')
            ->with('success', 'Broadcast berhasil dikembalikan dari arsip.');
    }

    public function destroy(
        Request $request,
        AffiliationBroadcast $broadcast,
        BroadcastImageService $broadcastImageService
    ) {
        $actor = $request->user();
        $creator = $broadcast->creator;

        $before = [
            'broadcast_id' => $broadcast->id,
            'status' => $broadcast->status,
            'title' => $broadcast->title,
            'created_by' => $broadcast->created_by,
            'target_mode' => $broadcast->target_mode,
        ];

        if ($broadcast->image_path) {
            $broadcastImageService->delete($broadcast->image_path);
        }

        $broadcast->delete();

        AuditLogger::log(
            actor: $actor,
            module: 'broadcasts',
            action: 'delete',
            targetUser: $creator,
            before: $before,
            after: [],
            context: [
                'reason' => 'moderasi super admin',
            ]
        );

        return redirect()
            ->route('endmin.broadcast-verifications.index')
            ->with('success', 'Broadcast berhasil dihapus.');
    }
}
