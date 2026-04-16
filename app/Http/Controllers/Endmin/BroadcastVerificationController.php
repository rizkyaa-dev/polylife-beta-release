<?php

namespace App\Http\Controllers\Endmin;

use App\Actions\Broadcast\ArchiveAffiliationBroadcastAction;
use App\Actions\Broadcast\DeleteAffiliationBroadcastAction;
use App\Actions\Broadcast\UnarchiveAffiliationBroadcastAction;
use App\Http\Controllers\Controller;
use App\Models\AffiliationBroadcast;
use App\Queries\Broadcast\BroadcastVerificationIndexQuery;
use App\Support\Endmin\AuditLogger;
use Illuminate\Http\Request;

class BroadcastVerificationController extends Controller
{
    public function __construct(
        private readonly BroadcastVerificationIndexQuery $broadcastVerificationIndexQuery,
        private readonly ArchiveAffiliationBroadcastAction $archiveAffiliationBroadcastAction,
        private readonly UnarchiveAffiliationBroadcastAction $unarchiveAffiliationBroadcastAction,
        private readonly DeleteAffiliationBroadcastAction $deleteAffiliationBroadcastAction
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $targetModeFilter = trim((string) $request->query('target_mode', ''));
        $payload = $this->broadcastVerificationIndexQuery->build($search, $statusFilter, $targetModeFilter);

        return view('endmin.broadcasts.index', [
            'broadcasts' => $payload['broadcasts'],
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'target_mode' => $targetModeFilter,
            ],
            'stats' => $payload['stats'],
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
        $beforeStatus = $broadcast->status;
        ($this->archiveAffiliationBroadcastAction)($broadcast);

        AuditLogger::log(
            actor: $request->user(),
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
        $beforeStatus = $broadcast->status;
        ($this->unarchiveAffiliationBroadcastAction)($broadcast);

        AuditLogger::log(
            actor: $request->user(),
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

    public function destroy(Request $request, AffiliationBroadcast $broadcast)
    {
        $creator = $broadcast->creator;
        $before = [
            'broadcast_id' => $broadcast->id,
            'status' => $broadcast->status,
            'title' => $broadcast->title,
            'created_by' => $broadcast->created_by,
            'target_mode' => $broadcast->target_mode,
        ];

        ($this->deleteAffiliationBroadcastAction)($broadcast);

        AuditLogger::log(
            actor: $request->user(),
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
