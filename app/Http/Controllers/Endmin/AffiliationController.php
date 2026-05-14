<?php

namespace App\Http\Controllers\Endmin;

use App\Actions\Affiliation\ApproveAffiliationRequestAction;
use App\Actions\Affiliation\RejectAffiliationRequestAction;
use App\Http\Controllers\Controller;
use App\Models\AffiliationRequest;
use App\Models\AffiliationTemplate;
use App\Models\User;
use App\Queries\User\AffiliationIndexQuery;
use App\Support\Affiliation\AffiliationNormalizer;
use App\Support\Endmin\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AffiliationController extends Controller
{
    public function __construct(
        private readonly AffiliationIndexQuery $affiliationIndexQuery,
        private readonly ApproveAffiliationRequestAction $approveAffiliationRequestAction,
        private readonly RejectAffiliationRequestAction $rejectAffiliationRequestAction,
        private readonly AffiliationNormalizer $affiliationNormalizer
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $templates = AffiliationTemplate::query()
            ->where('is_active', true)
            ->orderBy('affiliation_name')
            ->get(['id', 'affiliation_type', 'affiliation_name']);
        $pendingRequests = AffiliationRequest::query()
            ->with(['user', 'template'])
            ->where('status', AffiliationRequest::STATUS_PENDING)
            ->latest()
            ->paginate(10, ['*'], 'requests_page')
            ->withQueryString();

        return view('endmin.affiliations.index', [
            'affiliations' => $this->affiliationIndexQuery->build($search, $status),
            'pendingRequests' => $pendingRequests,
            'templates' => $templates,
            'templateSuggestions' => $this->templateSuggestions($pendingRequests, $templates),
            'affiliationTypeOptions' => $this->affiliationTypeOptions(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function manage(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', 'active'));
        $quality = trim((string) $request->query('quality', ''));

        $duplicateKeys = AffiliationTemplate::query()
            ->select('affiliation_type', 'normalized_name')
            ->whereNotNull('normalized_name')
            ->where('normalized_name', '!=', '')
            ->groupBy('affiliation_type', 'normalized_name')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn (AffiliationTemplate $template): string => $this->duplicateKey($template->affiliation_type, $template->normalized_name))
            ->all();

        $templates = AffiliationTemplate::query()
            ->withCount(['users', 'adminAssignments', 'broadcastTargets'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('affiliation_name', 'like', '%'.$search.'%')
                        ->orWhere('affiliation_type', 'like', '%'.$search.'%')
                        ->orWhere('normalized_name', 'like', '%'.$this->affiliationNormalizer->nameKey($search).'%');
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($quality === 'ghost', function (Builder $query): void {
                $query->doesntHave('users')
                    ->doesntHave('adminAssignments')
                    ->doesntHave('broadcastTargets')
                    ->whereDoesntHave('requests', fn (Builder $requestQuery) => $requestQuery->whereIn('status', [
                        AffiliationRequest::STATUS_PENDING,
                        AffiliationRequest::STATUS_APPROVED,
                    ]));
            })
            ->when($quality === 'duplicate' && $duplicateKeys !== [], function (Builder $query) use ($duplicateKeys): void {
                $query->where(function (Builder $duplicateQuery) use ($duplicateKeys): void {
                    foreach ($duplicateKeys as $key) {
                        [$type, $name] = explode('|', $key, 2);
                        $duplicateQuery->orWhere(function (Builder $itemQuery) use ($type, $name): void {
                            $type === '__NULL__'
                                ? $itemQuery->whereNull('affiliation_type')
                                : $itemQuery->where('affiliation_type', $type);

                            $itemQuery->where('normalized_name', $name);
                        });
                    }
                });
            })
            ->when($quality === 'duplicate' && $duplicateKeys === [], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('is_active')
            ->orderBy('affiliation_name')
            ->paginate(20)
            ->withQueryString();

        return view('endmin.affiliations.manage.index', [
            'templates' => $templates,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'quality' => $quality,
            ],
            'duplicateKeys' => $duplicateKeys,
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function create()
    {
        return view('endmin.affiliations.manage.form', [
            'template' => new AffiliationTemplate([
                'affiliation_type' => 'university',
                'aliases' => [],
                'is_active' => true,
            ]),
            'mode' => 'create',
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedTemplatePayload($request);

        $template = AffiliationTemplate::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log(
            actor: $request->user(),
            module: 'affiliation',
            action: 'template_create',
            after: $template->only([
                'id',
                'affiliation_type',
                'affiliation_name',
                'normalized_name',
                'aliases',
                'is_active',
            ])
        );

        return redirect()
            ->route('endmin.affiliations.manage.edit', $template)
            ->with('success', 'Afiliasi berhasil dibuat.');
    }

    public function edit(AffiliationTemplate $template)
    {
        $template->loadCount(['users', 'adminAssignments', 'broadcastTargets']);

        return view('endmin.affiliations.manage.form', [
            'template' => $template,
            'mode' => 'edit',
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function update(Request $request, AffiliationTemplate $template)
    {
        $validated = $this->validatedTemplatePayload($request, $template);
        $oldType = $template->affiliation_type;
        $oldName = $template->affiliation_name;
        $before = $template->only([
            'affiliation_type',
            'affiliation_name',
            'normalized_name',
            'aliases',
            'is_active',
            'merged_into_id',
        ]);

        DB::transaction(function () use ($request, $template, $validated, $oldType, $oldName, $before): void {
            $template->forceFill($validated)->save();
            $this->syncTemplateToRuntimeRecords($template, $oldType, $oldName);

            AuditLogger::log(
                actor: $request->user(),
                module: 'affiliation',
                action: 'template_update',
                before: $before,
                after: $template->fresh()->only([
                    'affiliation_type',
                    'affiliation_name',
                    'normalized_name',
                    'aliases',
                    'is_active',
                    'merged_into_id',
                ])
            );
        });

        return redirect()
            ->route('endmin.affiliations.manage.edit', $template)
            ->with('success', 'Afiliasi berhasil diperbarui dan disinkronkan.');
    }

    public function mergeForm(AffiliationTemplate $template)
    {
        $template->loadCount(['users', 'adminAssignments', 'broadcastTargets', 'requests']);

        $targets = AffiliationTemplate::query()
            ->whereKeyNot($template->id)
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->orderBy('affiliation_name')
            ->get(['id', 'affiliation_type', 'affiliation_name']);

        return view('endmin.affiliations.manage.merge', [
            'template' => $template,
            'targets' => $targets,
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function merge(Request $request, AffiliationTemplate $template)
    {
        $validated = $request->validate([
            'target_template_id' => [
                'required',
                'integer',
                Rule::exists('affiliation_templates', 'id')->where('is_active', true),
            ],
        ]);

        if ((int) $validated['target_template_id'] === (int) $template->id) {
            throw ValidationException::withMessages([
                'target_template_id' => 'Template tujuan harus berbeda.',
            ]);
        }

        $target = AffiliationTemplate::query()
            ->whereKey($validated['target_template_id'])
            ->where('is_active', true)
            ->whereNull('merged_into_id')
            ->firstOrFail();

        $before = $template->only([
            'id',
            'affiliation_type',
            'affiliation_name',
            'normalized_name',
            'is_active',
            'merged_into_id',
        ]);

        $counts = [
            'users' => $template->users()->count(),
            'admin_assignments' => $template->adminAssignments()->count(),
            'broadcast_targets' => $template->broadcastTargets()->count(),
            'requests' => $template->requests()->count(),
        ];

        DB::transaction(function () use ($request, $template, $target, $before, $counts): void {
            $payload = [
                'affiliation_template_id' => $target->id,
                'affiliation_type' => $target->affiliation_type,
                'affiliation_name' => $target->affiliation_name,
                'updated_at' => now(),
            ];

            DB::table('users')->where('affiliation_template_id', $template->id)->update($payload);

            $this->deleteCollidingAdminAssignments($template, $target);
            $this->deleteCollidingBroadcastTargets($template, $target);

            DB::table('admin_assignments')->where('affiliation_template_id', $template->id)->update($payload);
            DB::table('affiliation_broadcast_targets')->where('affiliation_template_id', $template->id)->update($payload);

            DB::table('affiliation_requests')
                ->where('affiliation_template_id', $template->id)
                ->update([
                    'affiliation_template_id' => $target->id,
                    'affiliation_type' => $target->affiliation_type,
                    'affiliation_name' => $target->affiliation_name,
                    'updated_at' => now(),
                ]);

            $template->forceFill([
                'is_active' => false,
                'merged_into_id' => $target->id,
                'merged_by' => $request->user()->id,
                'merged_at' => now(),
            ])->save();

            AuditLogger::log(
                actor: $request->user(),
                module: 'affiliation',
                action: 'template_merge',
                before: $before,
                after: $template->fresh()->only([
                    'id',
                    'affiliation_type',
                    'affiliation_name',
                    'normalized_name',
                    'is_active',
                    'merged_into_id',
                    'merged_by',
                    'merged_at',
                ]),
                context: [
                    'target_template' => $target->only(['id', 'affiliation_type', 'affiliation_name', 'normalized_name']),
                    'moved_counts' => $counts,
                ]
            );
        });

        return redirect()
            ->route('endmin.affiliations.manage.edit', $target)
            ->with('success', 'Afiliasi berhasil digabungkan ke '.$target->affiliation_name.'.');
    }

    public function destroy(AffiliationTemplate $template)
    {
        $before = $template->only(['id', 'affiliation_type', 'affiliation_name', 'normalized_name', 'is_active']);
        $relatedCount = $template->users()->count()
            + $template->adminAssignments()->count()
            + $template->broadcastTargets()->count()
            + $template->requests()->count();

        if ($relatedCount > 0) {
            $template->forceFill(['is_active' => false])->save();
            AuditLogger::log(
                actor: request()->user(),
                module: 'affiliation',
                action: 'template_deactivate',
                before: $before,
                after: $template->fresh()->only(['id', 'affiliation_type', 'affiliation_name', 'normalized_name', 'is_active']),
                context: ['related_count' => $relatedCount]
            );

            return redirect()
                ->route('endmin.affiliations.manage.index')
                ->with('success', 'Afiliasi sudah dipakai, jadi dinonaktifkan agar riwayat data tetap aman.');
        }

        $template->delete();
        AuditLogger::log(
            actor: request()->user(),
            module: 'affiliation',
            action: 'template_delete',
            before: $before
        );

        return redirect()
            ->route('endmin.affiliations.manage.index')
            ->with('success', 'Afiliasi kosong berhasil dihapus.');
    }

    public function extend(Request $request, string $affiliationName)
    {
        $affiliationName = trim(urldecode($affiliationName));
        $affiliationType = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $role = trim((string) $request->query('role', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);

        abort_if($affiliationName === '', 404);

        $baseQuery = User::query()
            ->where('affiliation_name', $affiliationName)
            ->when($affiliationType !== '', fn ($query) => $query->where('affiliation_type', $affiliationType));

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'verified' => (clone $baseQuery)->where('affiliation_status', 'verified')->count(),
            'pending' => (clone $baseQuery)->where('affiliation_status', 'pending')->count(),
            'admin' => (clone $baseQuery)->where('is_admin', User::ADMIN_LEVEL_ADMIN)->count(),
            'super_admin' => (clone $baseQuery)->where('is_admin', User::ADMIN_LEVEL_SUPER_ADMIN)->count(),
        ];

        abort_if($summary['total'] === 0, 404);

        $usersQuery = (clone $baseQuery)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('student_id_number', 'like', '%'.$search.'%');
                });
            })
            ->when(in_array($status, ['pending', 'verified', 'rejected'], true), fn ($query) => $query->where('affiliation_status', $status))
            ->when($role !== '', function ($query) use ($role) {
                match ($role) {
                    'user' => $query->where('is_admin', User::ADMIN_LEVEL_USER),
                    'admin' => $query->where('is_admin', User::ADMIN_LEVEL_ADMIN),
                    'super_admin' => $query->where('is_admin', User::ADMIN_LEVEL_SUPER_ADMIN),
                    default => null,
                };
            });

        return view('endmin.affiliations.extend', [
            'affiliationName' => $affiliationName,
            'affiliationType' => $affiliationType,
            'summary' => $summary,
            'users' => $usersQuery
                ->orderByDesc('is_admin')
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'role' => $role,
                'per_page' => $perPage,
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }

    public function batch(Request $request, string $affiliationName)
    {
        $affiliationName = trim(urldecode($affiliationName));
        $affiliationType = trim((string) $request->query('type', ''));

        abort_if($affiliationName === '', 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['verify', 'unverify'])],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ]);

        $users = User::query()
            ->whereIn('id', $validated['user_ids'])
            ->where('affiliation_name', $affiliationName)
            ->when($affiliationType !== '', fn ($query) => $query->where('affiliation_type', $affiliationType))
            ->get();

        foreach ($users as $user) {
            if ($validated['action'] === 'verify') {
                $user->forceFill([
                    'affiliation_status' => 'verified',
                    'affiliation_verified_at' => $user->affiliation_verified_at ?: now(),
                    'affiliation_verified_by' => $request->user()->id,
                    'affiliation_template_id' => $user->affiliation_template_id ?: $this->findTemplateId($user->affiliation_type, $user->affiliation_name),
                ])->save();
            } else {
                $user->forceFill([
                    'affiliation_status' => 'pending',
                    'affiliation_verified_at' => null,
                    'affiliation_verified_by' => null,
                ])->save();
            }
        }

        $message = $validated['action'] === 'verify'
            ? $users->count().' akun diverifikasi.'
            : $users->count().' verifikasi akun dibatalkan.';

        return redirect()
            ->route('endmin.affiliations.extend', array_filter([
                'affiliationName' => $affiliationName,
                'type' => $affiliationType ?: null,
            ]))
            ->with('success', $message);
    }

    public function approve(Request $httpRequest, AffiliationRequest $affiliationRequest)
    {
        abort_unless($affiliationRequest->isPending(), 404);

        $validated = $httpRequest->validate([
            'affiliation_template_id' => ['nullable', 'integer', Rule::exists('affiliation_templates', 'id')->where('is_active', true)],
            'canonical_affiliation_type' => ['nullable', Rule::in(array_keys($this->affiliationTypeOptions()))],
            'canonical_affiliation_name' => ['nullable', 'string', 'max:160'],
        ]);

        ($this->approveAffiliationRequestAction)(
            $httpRequest->user(),
            $affiliationRequest,
            $validated['affiliation_template_id'] ?? null,
            $validated['canonical_affiliation_type'] ?? null,
            $validated['canonical_affiliation_name'] ?? null
        );

        return redirect()->route('endmin.affiliations.index')->with('success', 'Pengajuan afiliasi disetujui.');
    }

    public function reject(Request $httpRequest, AffiliationRequest $affiliationRequest)
    {
        abort_unless($affiliationRequest->isPending(), 404);

        $validated = $httpRequest->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        ($this->rejectAffiliationRequestAction)(
            $httpRequest->user(),
            $affiliationRequest,
            $validated['rejection_reason'] ?? null
        );

        return redirect()->route('endmin.affiliations.index')->with('success', 'Pengajuan afiliasi ditolak.');
    }

    /**
     * @return array{affiliation_type: ?string, affiliation_name: string, aliases: array<int, string>, is_active: bool}
     */
    private function validatedTemplatePayload(Request $request, ?AffiliationTemplate $template = null): array
    {
        $validated = $request->validate([
            'affiliation_type' => ['nullable', Rule::in(['school', 'university', 'institute', 'polytechnic', 'academy', 'organization', 'company', 'foundation', 'other'])],
            'affiliation_name' => ['required', 'string', 'max:160'],
            'aliases_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = $this->affiliationNormalizer->type($validated['affiliation_type'] ?? null);
        $name = $this->affiliationNormalizer->displayName((string) $validated['affiliation_name']);
        $normalizedName = $this->affiliationNormalizer->nameKey($name);

        $duplicate = AffiliationTemplate::query()
            ->where('normalized_name', $normalizedName)
            ->whereNull('merged_into_id')
            ->when($type === null, fn (Builder $query) => $query->whereNull('affiliation_type'), fn (Builder $query) => $query->where('affiliation_type', $type))
            ->when($template, fn (Builder $query) => $query->whereKeyNot($template->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'affiliation_name' => 'Nama afiliasi dengan tipe yang sama sudah ada.',
            ]);
        }

        return [
            'affiliation_type' => $type,
            'affiliation_name' => $name,
            'normalized_name' => $normalizedName,
            'aliases' => $this->parseAliases((string) ($validated['aliases_text'] ?? '')),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function syncTemplateToRuntimeRecords(AffiliationTemplate $template, ?string $oldType, string $oldName): void
    {
        $payload = [
            'affiliation_template_id' => $template->id,
            'affiliation_type' => $template->affiliation_type,
            'affiliation_name' => $template->affiliation_name,
            'updated_at' => now(),
        ];

        foreach (['users', 'admin_assignments', 'affiliation_broadcast_targets'] as $table) {
            DB::table($table)
                ->where(function ($query) use ($template, $oldType, $oldName): void {
                    $query->where('affiliation_template_id', $template->id)
                        ->orWhere(function ($legacyQuery) use ($oldType, $oldName): void {
                            $legacyQuery->where('affiliation_name', $oldName);

                            $oldType === null
                                ? $legacyQuery->whereNull('affiliation_type')
                                : $legacyQuery->where('affiliation_type', $oldType);
                        });
                })
                ->update($payload);
        }
    }

    private function deleteCollidingAdminAssignments(AffiliationTemplate $source, AffiliationTemplate $target): void
    {
        DB::table('admin_assignments')
            ->where('affiliation_template_id', $source->id)
            ->orderBy('id')
            ->chunkById(100, function ($assignments) use ($target): void {
                foreach ($assignments as $assignment) {
                    $exists = DB::table('admin_assignments')
                        ->where('user_id', $assignment->user_id)
                        ->where('affiliation_name', $target->affiliation_name)
                        ->when(
                            $target->affiliation_type === null,
                            fn ($query) => $query->whereNull('affiliation_type'),
                            fn ($query) => $query->where('affiliation_type', $target->affiliation_type)
                        )
                        ->exists();

                    if ($exists) {
                        DB::table('admin_assignments')->where('id', $assignment->id)->delete();
                    }
                }
            });
    }

    private function deleteCollidingBroadcastTargets(AffiliationTemplate $source, AffiliationTemplate $target): void
    {
        DB::table('affiliation_broadcast_targets')
            ->where('affiliation_template_id', $source->id)
            ->orderBy('id')
            ->chunkById(100, function ($targets) use ($target): void {
                foreach ($targets as $broadcastTarget) {
                    $exists = DB::table('affiliation_broadcast_targets')
                        ->where('broadcast_id', $broadcastTarget->broadcast_id)
                        ->where('affiliation_name', $target->affiliation_name)
                        ->when(
                            $target->affiliation_type === null,
                            fn ($query) => $query->whereNull('affiliation_type'),
                            fn ($query) => $query->where('affiliation_type', $target->affiliation_type)
                        )
                        ->exists();

                    if ($exists) {
                        DB::table('affiliation_broadcast_targets')->where('id', $broadcastTarget->id)->delete();
                    }
                }
            });
    }

    private function findTemplateId(?string $type, ?string $name): ?int
    {
        $name = $this->normalizeName((string) $name);
        if ($name === '') {
            return null;
        }

        $type = $this->affiliationNormalizer->type($type);
        $normalizedName = $this->affiliationNormalizer->nameKey($name);
        $query = AffiliationTemplate::query()
            ->where('normalized_name', $normalizedName)
            ->whereNull('merged_into_id')
            ->when($type === null, fn (Builder $builder) => $builder->whereNull('affiliation_type'), fn (Builder $builder) => $builder->where('affiliation_type', $type));

        return $query->value('id');
    }

    /**
     * @return list<string>
     */
    private function parseAliases(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $items = preg_split('/[\r\n,]+/', $value) ?: [];

        return array_values(array_unique(array_filter(
            array_map(fn (string $item): string => $this->affiliationNormalizer->displayName($item), $items),
            fn (string $item): bool => $item !== ''
        )));
    }

    private function normalizeName(string $value): string
    {
        return $this->affiliationNormalizer->displayName($value);
    }

    private function nullableString(?string $value): ?string
    {
        return $this->affiliationNormalizer->type($value);
    }

    private function duplicateKey(?string $type, ?string $normalizedName): string
    {
        return ($type ?: '__NULL__').'|'.($normalizedName ?: '');
    }

    /**
     * @return array<string, string>
     */
    private function affiliationTypeOptions(): array
    {
        return [
            'school' => 'Sekolah',
            'university' => 'Universitas',
            'institute' => 'Institut',
            'polytechnic' => 'Politeknik',
            'academy' => 'Akademi',
            'organization' => 'Organisasi',
            'company' => 'Perusahaan',
            'foundation' => 'Yayasan',
            'other' => 'Lainnya',
        ];
    }

    /**
     * @return array<int, Collection<int, AffiliationTemplate>>
     */
    private function templateSuggestions(LengthAwarePaginator $requests, Collection $templates): array
    {
        $suggestions = [];

        foreach ($requests->items() as $request) {
            $requestTokens = $this->affiliationTokens((string) $request->affiliation_name);
            $requestNormalized = implode(' ', $requestTokens);
            $requestType = $this->inferAffiliationType((string) $request->affiliation_name, $request->affiliation_type);

            $matches = $templates
                ->map(function (AffiliationTemplate $template) use ($requestTokens, $requestNormalized, $requestType): array {
                    $templateType = $this->inferAffiliationType((string) $template->affiliation_name, $template->affiliation_type);
                    $storedTemplateType = $this->nullableString($template->affiliation_type);

                    if ($storedTemplateType && $templateType && $storedTemplateType !== $templateType) {
                        return ['template' => $template, 'score' => 0];
                    }

                    if ($requestType && $templateType && $requestType !== $templateType) {
                        return ['template' => $template, 'score' => 0];
                    }

                    $templateTokens = $this->affiliationTokens((string) $template->affiliation_name);
                    $templateNormalized = implode(' ', $templateTokens);
                    $overlap = count(array_intersect($requestTokens, $templateTokens));
                    $maxTokens = max(count($requestTokens), count($templateTokens), 1);
                    $score = $overlap / $maxTokens;

                    if ($requestNormalized !== '' && $templateNormalized !== '') {
                        if ($requestNormalized === $templateNormalized) {
                            $score += 2;
                        } elseif (str_contains($requestNormalized, $templateNormalized) || str_contains($templateNormalized, $requestNormalized)) {
                            $score += 0.75;
                        }
                    }

                    if ($requestType && $templateType === $requestType) {
                        $score += 0.35;
                    }

                    return ['template' => $template, 'score' => $score];
                })
                ->filter(fn (array $match): bool => $match['score'] >= 0.65)
                ->sortByDesc('score')
                ->take(3)
                ->pluck('template')
                ->values();

            $suggestions[$request->id] = $matches;
        }

        return $suggestions;
    }

    /**
     * @return list<string>
     */
    private function affiliationTokens(string $value): array
    {
        $normalized = mb_strtolower($value);
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?: '';
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?: '';

        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            explode(' ', $normalized),
            fn (string $token): bool => mb_strlen($token) >= 3
        )));
    }

    private function inferAffiliationType(string $name, ?string $fallbackType = null): ?string
    {
        $normalized = mb_strtolower($name);

        $keywordMap = [
            'polytechnic' => ['politeknik', 'polytechnic'],
            'university' => ['universitas', 'university'],
            'institute' => ['institut', 'institute'],
            'academy' => ['akademi', 'academy'],
            'school' => ['sekolah', 'sma', 'smk', 'smp', 'sd', 'madrasah', 'school'],
            'company' => ['pt ', 'cv ', 'perusahaan', 'company'],
            'foundation' => ['yayasan', 'foundation'],
        ];

        foreach ($keywordMap as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $type;
                }
            }
        }

        return $this->nullableString($fallbackType);
    }
}
