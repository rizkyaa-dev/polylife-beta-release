<?php

namespace App\Http\Controllers\Endmin;

use App\Actions\Affiliation\ApproveAffiliationRequestAction;
use App\Actions\Affiliation\RejectAffiliationRequestAction;
use App\Http\Controllers\Controller;
use App\Models\AffiliationRequest;
use App\Models\AffiliationTemplate;
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
