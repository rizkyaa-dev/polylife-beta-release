<?php

namespace App\Services\Ai\Eco;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\Ai\AiConversationBranchService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EcoContextTools
{
    public function __construct(
        private readonly EcoToolSelector $tools,
        private readonly EcoContextRelevance $relevance,
        private readonly EcoConversationMemory $memory,
        private readonly AiConversationBranchService $branches
    ) {}

    public function discover(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'domains' => ['required', 'array', 'min:1', 'max:9'],
            'domains.*' => ['required', 'string', 'distinct', Rule::in($this->tools->domainNames())],
        ])->validate();

        return $this->tools->discover($validated['domains']);
    }

    public function artifact(User $user, AiChatSession $session, ?int $headId, int $messageId, EcoContextPolicy $policy): ?AiChatMessage
    {
        $recalled = $this->recall($user, $session, $headId, ['message_ids' => [$messageId]], $policy);
        $source = collect($recalled['messages'])->firstWhere('artifact', true);

        return $source === null ? null : (new AiChatMessage)->forceFill([
            'id' => $source['message_id'], 'content' => $source['content'],
        ]);
    }

    public function recall(User $user, AiChatSession $session, ?int $headId, array $arguments, EcoContextPolicy $policy): array
    {
        abort_unless($session->user_id === $user->id, 404);
        $validated = Validator::make($arguments, [
            'query' => ['required_without:message_ids', 'string', 'min:1', 'max:500'],
            'message_ids' => ['required_without:query', 'array', 'min:1', 'max:4'],
            'message_ids.*' => ['integer', 'min:1', 'distinct'],
        ])->validate();
        $messages = $this->branches->recentLineage($session, $headId, $policy->retrievalMessageLimit)
            ->where('status', 'completed')->whereIn('role', ['user', 'assistant']);
        $ids = array_map('intval', $validated['message_ids'] ?? []);
        if ($ids !== []) {
            if (count(array_intersect($ids, $messages->pluck('id')->all())) !== count($ids)) {
                throw ValidationException::withMessages(['message_ids' => 'Pesan tidak tersedia pada cabang percakapan ini.']);
            }
            $matches = $messages->whereIn('id', $ids);
        } else {
            $terms = $this->relevance->terms($validated['query']);
            $matches = $messages->filter(fn ($message) => $this->relevance->score($message->content, $terms) > 0)
                ->sortByDesc(fn ($message) => $this->relevance->score($message->content, $terms))->take(4)->sortBy('id');
        }
        $results = [];
        $used = 0;
        $omitted = [];
        foreach ($matches as $message) {
            $length = mb_strlen($message->content);
            if ($used + $length > 120000) {
                $omitted[] = $message->id;

                continue;
            }
            $used += $length;
            $results[] = [
                'message_id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'artifact' => $message->role === 'assistant' && $this->memory->isArtifact($message->content),
            ];
        }

        return [
            'status' => $omitted === [] ? 'completed' : 'partial',
            'evidence_type' => 'historical_conversation_only',
            'messages' => $results,
            'omitted_message_ids' => $omitted,
            'message' => $omitted === [] ? 'Kutipan asli; verifikasi data workspace terbaru dengan read tool.' : 'Bukti tidak dipotong. Minta satu ID atau query lebih spesifik untuk pesan yang belum dimuat.',
        ];
    }
}
