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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
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
            'canonical_affiliation_name' => ['nullable', 'string', 'max:160'],
        ]);

        ($this->approveAffiliationRequestAction)(
            $httpRequest->user(),
            $affiliationRequest,
            $validated['affiliation_template_id'] ?? null,
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
     * @return array<int, Collection<int, AffiliationTemplate>>
     */
    private function templateSuggestions(LengthAwarePaginator $requests, Collection $templates): array
    {
        $suggestions = [];

        foreach ($requests->items() as $request) {
            $requestTokens = $this->affiliationTokens((string) $request->affiliation_name);
            $requestNormalized = implode(' ', $requestTokens);

            $matches = $templates
                ->map(function (AffiliationTemplate $template) use ($requestTokens, $requestNormalized): array {
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

                    return ['template' => $template, 'score' => $score];
                })
                ->filter(fn (array $match): bool => $match['score'] >= 0.5)
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
}
