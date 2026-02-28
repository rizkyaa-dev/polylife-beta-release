<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchAffiliationBroadcastPushJob;
use App\Models\AdminAssignment;
use App\Models\AffiliationBroadcast;
use App\Models\User;
use App\Services\BroadcastImageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AffiliationBroadcastController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $targetFilter = trim((string) $request->query('target', ''));

        $broadcastsQuery = AffiliationBroadcast::query()
            ->with(['creator:id,name,email', 'targets']);

        if (! $actor->isSuperAdmin()) {
            $broadcastsQuery->where('created_by', $actor->id);
        }

        if (in_array($statusFilter, [
            AffiliationBroadcast::STATUS_DRAFT,
            AffiliationBroadcast::STATUS_PUBLISHED,
            AffiliationBroadcast::STATUS_ARCHIVED,
        ], true)) {
            $broadcastsQuery->where('status', $statusFilter);
        }

        if ($search !== '') {
            $broadcastsQuery->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('creator', function ($creatorQuery) use ($search): void {
                        $creatorQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($targetFilter !== '') {
            $broadcastsQuery->where(function ($query) use ($targetFilter): void {
                $query->where('target_mode', AffiliationBroadcast::TARGET_MODE_GLOBAL)
                    ->orWhereHas('targets', function ($targetQuery) use ($targetFilter): void {
                        $targetQuery->where('affiliation_name', 'like', '%'.$targetFilter.'%');
                    });
            });
        }

        $broadcasts = $broadcastsQuery
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $summaryQuery = AffiliationBroadcast::query();
        if (! $actor->isSuperAdmin()) {
            $summaryQuery->where('created_by', $actor->id);
        }

        return view('admin.broadcasts.index', [
            'broadcasts' => $broadcasts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'target' => $targetFilter,
            ],
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'draft' => (clone $summaryQuery)->where('status', AffiliationBroadcast::STATUS_DRAFT)->count(),
                'published' => (clone $summaryQuery)->where('status', AffiliationBroadcast::STATUS_PUBLISHED)->count(),
                'archived' => (clone $summaryQuery)->where('status', AffiliationBroadcast::STATUS_ARCHIVED)->count(),
            ],
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }

    public function create(Request $request)
    {
        $actor = $request->user();
        [$targetOptions, $canUseGlobal] = $this->targetOptionsForActor($actor);
        $creationBlocked = $targetOptions === [] && ! $canUseGlobal;

        return view('admin.broadcasts.create', [
            'targetOptions' => $targetOptions,
            'canUseGlobal' => $canUseGlobal,
            'creationBlocked' => $creationBlocked,
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }

    public function store(Request $request, BroadcastImageService $broadcastImageService)
    {
        $actor = $request->user();
        [$targetOptions, $canUseGlobal] = $this->targetOptionsForActor($actor);

        if ($targetOptions === [] && ! $canUseGlobal) {
            throw ValidationException::withMessages([
                'targets' => 'Akun admin belum memiliki assignment afiliasi aktif. Hubungi super admin untuk menetapkan afiliasi.',
            ]);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'target_mode' => ['nullable', Rule::in([
                AffiliationBroadcast::TARGET_MODE_AFFILIATION,
                AffiliationBroadcast::TARGET_MODE_GLOBAL,
            ])],
            'targets' => ['nullable', 'array'],
            'targets.*' => ['string', 'max:220'],
            'send_push' => ['nullable', 'boolean'],
            'publish_now' => ['nullable', 'boolean'],
        ]);

        $targetMode = $this->resolveTargetMode(
            actor: $actor,
            requestedTargetMode: (string) ($validated['target_mode'] ?? AffiliationBroadcast::TARGET_MODE_AFFILIATION),
            canUseGlobal: $canUseGlobal
        );

        $selectedTargets = $this->resolveSelectedTargets(
            rawTargets: (array) ($validated['targets'] ?? []),
            targetOptions: $targetOptions,
            targetMode: $targetMode
        );

        if ($targetMode === AffiliationBroadcast::TARGET_MODE_AFFILIATION && $selectedTargets === []) {
            throw ValidationException::withMessages([
                'targets' => 'Pilih minimal satu target afiliasi.',
            ]);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $broadcastImageService->storeOptimized($request->file('image'));
        }

        $publishNow = $request->boolean('publish_now');
        $broadcast = AffiliationBroadcast::query()->create([
            'created_by' => $actor->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'image_path' => $imagePath,
            'target_mode' => $targetMode,
            'send_push' => $request->boolean('send_push'),
            'status' => $publishNow ? AffiliationBroadcast::STATUS_PUBLISHED : AffiliationBroadcast::STATUS_DRAFT,
            'published_at' => $publishNow ? now() : null,
        ]);

        if ($selectedTargets !== []) {
            $broadcast->targets()->createMany($selectedTargets);
        }

        if ($publishNow && $broadcast->send_push) {
            DispatchAffiliationBroadcastPushJob::dispatch($broadcast->id);
        }

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

        [$targetOptions, $canUseGlobal] = $this->targetOptionsForActor($actor);

        return view('admin.broadcasts.edit', [
            'broadcast' => $broadcast->load('targets'),
            'targetOptions' => $targetOptions,
            'canUseGlobal' => $canUseGlobal,
            'selectedTargetValues' => $broadcast->targets
                ->map(fn ($target) => $this->encodeTargetValue($target->affiliation_type, $target->affiliation_name))
                ->values()
                ->all(),
            'sidebarView' => 'layouts.components.admin-sidebar',
        ]);
    }

    public function update(
        Request $request,
        AffiliationBroadcast $broadcast,
        BroadcastImageService $broadcastImageService
    )
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $broadcast);

        if (! $broadcast->isDraft()) {
            return redirect()
                ->route('admin.broadcasts.show', $broadcast)
                ->withErrors(['broadcast' => 'Broadcast yang sudah dipublish tidak bisa diubah.']);
        }

        [$targetOptions, $canUseGlobal] = $this->targetOptionsForActor($actor);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'target_mode' => ['nullable', Rule::in([
                AffiliationBroadcast::TARGET_MODE_AFFILIATION,
                AffiliationBroadcast::TARGET_MODE_GLOBAL,
            ])],
            'targets' => ['nullable', 'array'],
            'targets.*' => ['string', 'max:220'],
            'send_push' => ['nullable', 'boolean'],
            'publish_now' => ['nullable', 'boolean'],
        ]);

        $targetMode = $this->resolveTargetMode(
            actor: $actor,
            requestedTargetMode: (string) ($validated['target_mode'] ?? AffiliationBroadcast::TARGET_MODE_AFFILIATION),
            canUseGlobal: $canUseGlobal
        );

        $selectedTargets = $this->resolveSelectedTargets(
            rawTargets: (array) ($validated['targets'] ?? []),
            targetOptions: $targetOptions,
            targetMode: $targetMode
        );

        if ($targetMode === AffiliationBroadcast::TARGET_MODE_AFFILIATION && $selectedTargets === []) {
            throw ValidationException::withMessages([
                'targets' => 'Pilih minimal satu target afiliasi.',
            ]);
        }

        if ($request->boolean('remove_image') && $broadcast->image_path) {
            $broadcastImageService->delete($broadcast->image_path);
            $broadcast->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($broadcast->image_path) {
                $broadcastImageService->delete($broadcast->image_path);
            }
            $broadcast->image_path = $broadcastImageService->storeOptimized($request->file('image'));
        }

        $publishNow = $request->boolean('publish_now');

        $broadcast->title = $validated['title'];
        $broadcast->body = $validated['body'];
        $broadcast->target_mode = $targetMode;
        $broadcast->send_push = $request->boolean('send_push');
        if ($publishNow) {
            $broadcast->status = AffiliationBroadcast::STATUS_PUBLISHED;
            $broadcast->published_at = now();
        }
        $broadcast->save();

        $broadcast->targets()->delete();
        if ($selectedTargets !== []) {
            $broadcast->targets()->createMany($selectedTargets);
        }

        if ($publishNow && $broadcast->send_push) {
            DispatchAffiliationBroadcastPushJob::dispatch($broadcast->id);
        }

        return redirect()
            ->route('admin.broadcasts.show', $broadcast)
            ->with('success', $publishNow ? 'Broadcast berhasil dipublish.' : 'Draft broadcast berhasil diperbarui.');
    }

    public function publish(Request $request, AffiliationBroadcast $broadcast)
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $broadcast);

        if (! $broadcast->isDraft()) {
            return back()->withErrors(['broadcast' => 'Hanya broadcast draft yang bisa dipublish.']);
        }

        if (
            $broadcast->target_mode === AffiliationBroadcast::TARGET_MODE_AFFILIATION
            && ! $broadcast->targets()->exists()
        ) {
            return back()->withErrors(['broadcast' => 'Broadcast belum memiliki target afiliasi.']);
        }

        $broadcast->status = AffiliationBroadcast::STATUS_PUBLISHED;
        $broadcast->published_at = now();
        $broadcast->save();

        if ($broadcast->send_push) {
            DispatchAffiliationBroadcastPushJob::dispatch($broadcast->id);
        }

        return back()->with('success', 'Broadcast berhasil dipublish.');
    }

    public function archive(Request $request, AffiliationBroadcast $broadcast)
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $broadcast);

        if ($broadcast->isArchived()) {
            return back()->with('success', 'Broadcast sudah berada pada status arsip.');
        }

        $broadcast->status = AffiliationBroadcast::STATUS_ARCHIVED;
        $broadcast->save();

        return back()->with('success', 'Broadcast berhasil diarsipkan.');
    }

    public function destroy(
        Request $request,
        AffiliationBroadcast $broadcast,
        BroadcastImageService $broadcastImageService
    )
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $broadcast);

        if ($broadcast->image_path) {
            $broadcastImageService->delete($broadcast->image_path);
        }

        $broadcast->delete();

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

    private function targetOptionsForActor(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            $rawOptions = User::query()
                ->select('affiliation_type', 'affiliation_name')
                ->whereNotNull('affiliation_name')
                ->where('affiliation_name', '!=', '')
                ->distinct()
                ->orderBy('affiliation_name')
                ->get();

            return [$this->mapTargetOptions($rawOptions->all()), true];
        }

        $rawOptions = AdminAssignment::query()
            ->select('affiliation_type', 'affiliation_name')
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->whereNotNull('affiliation_name')
            ->where('affiliation_name', '!=', '')
            ->distinct()
            ->orderBy('affiliation_name')
            ->get();

        if ($rawOptions->isEmpty() && filled($actor->affiliation_name)) {
            $rawOptions = collect([
                (object) [
                    'affiliation_type' => $actor->affiliation_type,
                    'affiliation_name' => $actor->affiliation_name,
                ],
            ]);
        }

        return [$this->mapTargetOptions($rawOptions->all()), false];
    }

    private function mapTargetOptions(array $rawOptions): array
    {
        $options = [];
        $seen = [];

        foreach ($rawOptions as $option) {
            $type = filled($option->affiliation_type ?? null)
                ? trim((string) $option->affiliation_type)
                : null;
            $name = trim((string) ($option->affiliation_name ?? ''));

            if ($name === '') {
                continue;
            }

            $key = $this->encodeTargetValue($type, $name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $labelPrefix = $type ? strtoupper($type).' - ' : '';
            $options[] = [
                'value' => $key,
                'affiliation_type' => $type,
                'affiliation_name' => $name,
                'label' => $labelPrefix.$name,
            ];
        }

        return $options;
    }

    private function resolveTargetMode(User $actor, string $requestedTargetMode, bool $canUseGlobal): string
    {
        if (! $actor->isSuperAdmin()) {
            return AffiliationBroadcast::TARGET_MODE_AFFILIATION;
        }

        if (! $canUseGlobal) {
            return AffiliationBroadcast::TARGET_MODE_AFFILIATION;
        }

        return $requestedTargetMode === AffiliationBroadcast::TARGET_MODE_GLOBAL
            ? AffiliationBroadcast::TARGET_MODE_GLOBAL
            : AffiliationBroadcast::TARGET_MODE_AFFILIATION;
    }

    private function resolveSelectedTargets(array $rawTargets, array $targetOptions, string $targetMode): array
    {
        if ($targetMode === AffiliationBroadcast::TARGET_MODE_GLOBAL) {
            return [];
        }

        $allowedMap = collect($targetOptions)
            ->mapWithKeys(fn ($option) => [$option['value'] => $option])
            ->all();

        $selected = [];
        foreach (array_unique(array_map('strval', $rawTargets)) as $rawTargetValue) {
            $targetValue = trim($rawTargetValue);
            if ($targetValue === '') {
                continue;
            }

            if (! array_key_exists($targetValue, $allowedMap)) {
                throw ValidationException::withMessages([
                    'targets' => 'Terdapat target afiliasi yang tidak diizinkan.',
                ]);
            }

            $selected[] = [
                'affiliation_type' => $allowedMap[$targetValue]['affiliation_type'],
                'affiliation_name' => $allowedMap[$targetValue]['affiliation_name'],
            ];
        }

        return $selected;
    }

    private function encodeTargetValue(?string $affiliationType, string $affiliationName): string
    {
        return ($affiliationType ?: '').'||'.$affiliationName;
    }
}
