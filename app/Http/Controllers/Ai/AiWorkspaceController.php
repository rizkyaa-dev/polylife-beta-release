<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiChatSession;
use App\Models\UserAiAssistant;
use App\Services\Ai\AiConversationBranchService;
use App\Services\Ai\Enums\ThinkingEffort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiWorkspaceController extends Controller
{
    public function __construct(private readonly AiConversationBranchService $branchService) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // Rendering a GET request must stay side-effect free. This matters for
        // browser prefetch/prerender requests, which may never be activated.
        $assistant = UserAiAssistant::query()->firstOrNew(
            ['user_id' => $user->id],
            [
                'assistant_name' => 'PolyBot',
                'personality_tone' => 'friendly_peer',
            ]
        );

        $sessionId = $request->query('session');
        $currentSession = null;
        $activeRun = null;

        if ($sessionId) {
            $currentSession = AiChatSession::query()
                ->where('user_id', $user->id)
                ->find($sessionId);
        }

        if (! $currentSession && ! $request->boolean('new')) {
            $currentSession = AiChatSession::query()
                ->where('user_id', $user->id)
                ->latest('updated_at')
                ->latest('id')
                ->first();
        }

        if ($currentSession) {
            $lineage = $this->branchService->recentActiveLineage($currentSession, limit: 101)
                ->filter(fn ($message) => $message->status === 'completed' && in_array($message->role, ['user', 'assistant'], true))
                ->values();
            $messages = $lineage->take(-100)->values();
            $this->decorateMessages($currentSession, $messages);
            $currentSession->setRelation(
                'messages',
                $messages
            );
            $currentSession->setRelation(
                'proposals',
                $currentSession->proposals()
                    ->whereIn('action_id', $currentSession->messages
                        ->flatMap(fn ($message) => $message->tool_calls_json ?? [])
                        ->pluck('action_id')
                        ->filter())
                    ->get()
            );
            $activeRun = $currentSession->runs()
                ->where('status', 'running')
                ->with('userMessage')
                ->latest('id')
                ->first();
        }

        $sessions = AiChatSession::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('ai.workspace', [
            'assistant' => $assistant,
            'currentSession' => $currentSession,
            'activeRun' => $activeRun,
            'sessions' => $sessions,
            'thinkingEfforts' => ThinkingEffort::cases(),
            'thinkingSupported' => strtolower((string) config('services.ai_provider')) === 'deepseek',
            'hasEarlierMessages' => isset($lineage) && $lineage->count() > 100,
        ]);
    }

    public function messages(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate(['before' => ['required', 'integer']]);
        $currentSession = AiChatSession::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($session);
        $result = $this->branchService->completedPageBefore(
            $currentSession,
            (int) $validated['before'],
            100
        );
        $page = $result['messages'];
        $this->decorateMessages($currentSession, $page);
        $proposalStates = $this->proposalStates($currentSession, $page);
        $html = $page->map(fn ($message) => view('ai.partials.message', [
            'message' => $message,
            'proposalStates' => $proposalStates,
        ])->render())->implode('');

        return response()->json([
            'status' => 'success',
            'html' => $html,
            'has_more' => $result['has_more'],
            'next_before' => $page->first()?->id,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assistant_name' => ['required', 'string', 'max:50'],
            'personality_tone' => ['required', 'in:friendly_peer,casual,formal,strict_coach'],
            'thinking_effort' => ['required', Rule::enum(ThinkingEffort::class)],
            'custom_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        UserAiAssistant::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return back()->with('success', 'Pengaturan asisten berhasil diperbarui.');
    }

    public function updateThinking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'thinking_effort' => ['required', Rule::enum(ThinkingEffort::class)],
        ]);

        $assistant = UserAiAssistant::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['thinking_effort' => $validated['thinking_effort']]
        );

        return response()->json([
            'status' => 'success',
            'thinking_effort' => $assistant->thinking_effort->value,
            'label' => $assistant->thinking_effort->label(),
        ]);
    }

    private function decorateMessages(AiChatSession $session, $messages): void
    {
        $revisionGroups = $messages->where('role', 'user')->pluck('revision_group_id')->filter()->unique();
        $revisions = $revisionGroups->isEmpty()
            ? collect()
            : $session->messages()
                ->where('role', 'user')
                ->where('status', 'completed')
                ->whereIn('revision_group_id', $revisionGroups)
                ->oldest('id')
                ->get()
                ->groupBy('revision_group_id');
        $runs = $session->runs()
            ->whereIn('assistant_message_id', $messages->where('role', 'assistant')->pluck('id'))
            ->with('steps')
            ->get()
            ->keyBy('assistant_message_id');

        foreach ($messages as $message) {
            if ($message->role === 'user') {
                $message->setRelation('revisions', $revisions->get($message->revision_group_id, collect([$message])));
            } else {
                $message->setRelation('run', $runs->get($message->id));
            }
        }
    }

    private function proposalStates(AiChatSession $session, $messages)
    {
        $actionIds = $messages
            ->flatMap(fn ($message) => $message->tool_calls_json ?? [])
            ->pluck('action_id')
            ->filter();

        return $session->proposals()->whereIn('action_id', $actionIds)->get()->keyBy('action_id');
    }
}
