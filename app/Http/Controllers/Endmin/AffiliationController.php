<?php

namespace App\Http\Controllers\Endmin;

use App\Actions\Affiliation\ApproveAffiliationRequestAction;
use App\Actions\Affiliation\RejectAffiliationRequestAction;
use App\Http\Controllers\Controller;
use App\Models\AffiliationRequest;
use App\Models\AffiliationTemplate;
use App\Models\User;
use App\Queries\User\AffiliationIndexQuery;
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
        private readonly RejectAffiliationRequestAction $rejectAffiliationRequestAction
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

        $templates = AffiliationTemplate::query()
            ->withCount(['users', 'adminAssignments', 'broadcastTargets'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('affiliation_name', 'like', '%'.$search.'%')
                        ->orWhere('affiliation_type', 'like', '%'.$search.'%');
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('affiliation_name')
            ->paginate(20)
            ->withQueryString();

        return view('endmin.affiliations.manage.index', [
            'templates' => $templates,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
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

        DB::transaction(function () use ($template, $validated, $oldType, $oldName): void {
            $template->forceFill($validated)->save();
            $this->syncTemplateToRuntimeRecords($template, $oldType, $oldName);
        });

        return redirect()
            ->route('endmin.affiliations.manage.edit', $template)
            ->with('success', 'Afiliasi berhasil diperbarui dan disinkronkan.');
    }

    public function destroy(AffiliationTemplate $template)
    {
        $relatedCount = $template->users()->count()
            + $template->adminAssignments()->count()
            + $template->broadcastTargets()->count()
            + $template->requests()->count();

        if ($relatedCount > 0) {
            $template->forceFill(['is_active' => false])->save();

            return redirect()
                ->route('endmin.affiliations.manage.index')
                ->with('success', 'Afiliasi sudah dipakai, jadi dinonaktifkan agar riwayat data tetap aman.');
        }

        $template->delete();

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

        $type = $this->nullableString($validated['affiliation_type'] ?? null);
        $name = $this->normalizeName((string) $validated['affiliation_name']);

        $duplicate = AffiliationTemplate::query()
            ->where('affiliation_name', $name)
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

    private function findTemplateId(?string $type, ?string $name): ?int
    {
        $name = $this->normalizeName((string) $name);
        if ($name === '') {
            return null;
        }

        $type = $this->nullableString($type);
        $query = AffiliationTemplate::query()
            ->where('affiliation_name', $name)
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
            array_map(fn (string $item): string => $this->normalizeName($item), $items),
            fn (string $item): bool => $item !== ''
        )));
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: trim($value);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
