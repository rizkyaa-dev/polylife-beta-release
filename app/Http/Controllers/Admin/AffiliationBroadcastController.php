<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Broadcast\ArchiveAffiliationBroadcastAction;
use App\Actions\Broadcast\DeleteAffiliationBroadcastAction;
use App\Actions\Broadcast\PublishAffiliationBroadcastAction;
use App\Actions\Broadcast\StoreAffiliationBroadcastAction;
use App\Actions\Broadcast\UpdateAffiliationBroadcastAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Broadcast\StoreAffiliationBroadcastRequest;
use App\Http\Requests\Broadcast\UpdateAffiliationBroadcastRequest;
use App\Models\AffiliationBroadcast;
use App\Models\User;
use App\Queries\Broadcast\AdminBroadcastIndexQuery;
use App\Queries\Broadcast\BroadcastTargetOptionsQuery;
use Illuminate\Http\Request;

class AffiliationBroadcastController extends Controller
{
    public function __construct(
        private readonly AdminBroadcastIndexQuery $adminBroadcastIndexQuery,
        private readonly BroadcastTargetOptionsQuery $broadcastTargetOptionsQuery,
        private readonly PublishAffiliationBroadcastAction $publishAffiliationBroadcastAction,
        private readonly ArchiveAffiliationBroadcastAction $archiveAffiliationBroadcastAction,
        private readonly DeleteAffiliationBroadcastAction $deleteAffiliationBroadcastAction,
        private readonly StoreAffiliationBroadcastAction $storeAffiliationBroadcastAction,
        private readonly UpdateAffiliationBroadcastAction $updateAffiliationBroadcastAction
    ) {
    }

    public function index(Request $request)
    {
        $actor = $request->user();
        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $targetFilter = trim((string) $request->query('target', ''));
        $payload = $this->adminBroadcastIndexQuery->build($actor, $search, $statusFilter, $targetFilter);

        return view('admin.broadcasts.index', [
            'broadcasts' => $payload['broadcasts'],
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'target' => $targetFilter,
            ],
            'summary' => $payload['summary'],
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }

    public function create(Request $request)
    {
        $targetContext = $this->broadcastTargetOptionsQuery->forActor($request->user());

        return view('admin.broadcasts.create', [
            'targetOptions' => $targetContext['targetOptions'],
            'canUseGlobal' => $targetContext['canUseGlobal'],
            'creationBlocked' => $targetContext['creationBlocked'],
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }

    public function store(StoreAffiliationBroadcastRequest $request)
    {
        $publishNow = $request->boolean('publish_now');
        $broadcast = ($this->storeAffiliationBroadcastAction)(
            $request->user(),
            $request->validated(),
            $request->file('image'),
            $request->boolean('send_push'),
            $publishNow
        );

        return redirect()
            ->route('admin.broadcasts.show', $broadcast)
            ->with('success', $publishNow ? 'Broadcast berhasil dipublish.' : 'Broadcast berhasil disimpan sebagai draft.');
    }

    public function show(Request $request, AffiliationBroadcast $broadcast)
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $broadcast);

        $broadcast->load(['creator:id,name,email', 'targets']);
        $pushLogs = $broadcast->pushLogs()
            ->with('user:id,name,email')
            ->latest()
            ->paginate(20);

        return view('admin.broadcasts.show', [
            'broadcast' => $broadcast,
            'pushLogs' => $pushLogs,
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }

    public function edit(Request $request, AffiliationBroadcast $broadcast)
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $broadcast);

        if (! $broadcast->isDraft()) {
            return redirect()
                ->route('admin.broadcasts.show', $broadcast)
                ->withErrors(['broadcast' => 'Hanya broadcast draft yang bisa diedit.']);
        }

        $broadcast->load('targets');
        $targetContext = $this->broadcastTargetOptionsQuery->forActor($actor);

        return view('admin.broadcasts.edit', [
            'broadcast' => $broadcast,
            'targetOptions' => $targetContext['targetOptions'],
            'canUseGlobal' => $targetContext['canUseGlobal'],
            'selectedTargetValues' => $this->broadcastTargetOptionsQuery->selectedValues($broadcast),
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }

    public function update(
        UpdateAffiliationBroadcastRequest $request,
        AffiliationBroadcast $broadcast
    ) {
        $actor = $request->user();
        $this->ensureCanManage($actor, $broadcast);

        if (! $broadcast->isDraft()) {
            return redirect()
                ->route('admin.broadcasts.show', $broadcast)
                ->withErrors(['broadcast' => 'Broadcast yang sudah dipublish tidak bisa diubah.']);
        }

        $publishNow = $request->boolean('publish_now');
        ($this->updateAffiliationBroadcastAction)(
            $broadcast,
            $actor,
            $request->validated(),
            $request->file('image'),
            $request->boolean('remove_image'),
            $request->boolean('send_push'),
            $publishNow
        );

        return redirect()
            ->route('admin.broadcasts.show', $broadcast)
            ->with('success', $publishNow ? 'Broadcast berhasil dipublish.' : 'Draft broadcast berhasil diperbarui.');
    }

    public function publish(Request $request, AffiliationBroadcast $broadcast)
    {
        $this->ensureCanManage($request->user(), $broadcast);
        ($this->publishAffiliationBroadcastAction)($broadcast);

        return back()->with('success', 'Broadcast berhasil dipublish.');
    }

    public function archive(Request $request, AffiliationBroadcast $broadcast)
    {
        $this->ensureCanManage($request->user(), $broadcast);
        $archived = ($this->archiveAffiliationBroadcastAction)($broadcast);

        return back()->with('success', $archived
            ? 'Broadcast berhasil diarsipkan.'
            : 'Broadcast sudah berada pada status arsip.');
    }

    public function destroy(Request $request, AffiliationBroadcast $broadcast)
    {
        $this->ensureCanManage($request->user(), $broadcast);
        ($this->deleteAffiliationBroadcastAction)($broadcast);

        return redirect()
            ->route('admin.broadcasts.index')
            ->with('success', 'Broadcast berhasil dihapus.');
    }

    private function ensureCanManage(User $actor, AffiliationBroadcast $broadcast): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ((int) $broadcast->created_by !== (int) $actor->id) {
            abort(403, 'Akses ditolak');
        }
    }
}
