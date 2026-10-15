<?php

namespace App\Services\Ai\Eco;

use App\Models\AiChatBranch;
use App\Models\AiChatMessage;
use App\Models\AiChatRunStep;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\Science\AiScienceDelegation;
use Illuminate\Support\Collection;

final class EcoContextAssembler
{
    public function __construct(
        private readonly EcoConversationMemory $memory,
        private readonly EcoContextRelevance $relevance,
        private readonly AiScienceDelegation $science
    ) {}

    public function assemble(Collection $lineage, AiChatBranch $branch, string $prompt, string $instruction, array $tools, EcoContextPolicy $policy): array
    {
        $completed = $lineage->where('status', 'completed')->whereIn('role', ['user', 'assistant'])->values();
        $memory = $this->memory->read($completed, $branch);
        $chunks = [];
        foreach ($completed as $message) {
            if ($message->role === 'user' || $chunks === []) {
                $chunks[] = [];
            }
            $chunks[array_key_last($chunks)][] = $message;
        }
        $recent = array_slice($chunks, -$policy->recentTurns);
        $older = array_slice($chunks, 0, max(0, count($chunks) - count($recent)));
        $query = $prompt;
        if ($this->relevance->isFollowUp($prompt)) {
            $query .= ' '.$completed->where('role', 'user')->reverse()->take(2)->pluck('content')->implode(' ');
        }
        $terms = $this->relevance->terms($query);
        $selected = collect($recent)->flatten(1)->keyBy('id');
        $fixedCharacters = mb_strlen($instruction) + mb_strlen($prompt) + mb_strlen(json_encode($tools, JSON_THROW_ON_ERROR));
        $budget = max(0, $policy->tokenBudget * 3 - $fixedCharacters - $policy->memoryTokenBudget * 3);
        $used = $this->length($selected);
        $ranked = collect($older)->map(fn ($chunk) => [
            'messages' => $chunk,
            'score' => array_sum(array_map(fn ($message) => $this->relevance->score($message->content, $terms), $chunk)),
        ])->where('score', '>', 0)->sortByDesc('score')->take(4);
        foreach ($ranked as $candidate) {
            $length = $this->length(collect($candidate['messages']));
            if ($used + $length <= $budget) {
                foreach ($candidate['messages'] as $message) {
                    $selected->put($message->id, $message);
                }
                $used += $length;
            }
        }
        $selected = $selected->sortBy('id');
        $evidence = $this->evidence($memory['entries'], $completed, $selected, $terms, $policy);
        $history = $evidence === [] ? [] : [new LlmMessage('user', '<conversation_evidence>'.json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).'</conversation_evidence>')];
        foreach ($selected as $message) {
            $history[] = $this->message($message);
        }
        $estimated = (int) ceil(($fixedCharacters + array_sum(array_map(fn ($message) => mb_strlen($message->content ?? '') + mb_strlen($message->reasoningContent ?? ''), $history))) / 3);

        return [
            'history' => $history,
            'metadata' => [
                'mode' => 'eco', 'policy_version' => EcoContextPolicy::VERSION,
                'source_messages' => $completed->count(), 'selected_message_ids' => $selected->keys()->values()->all(),
                'tool_declarations' => count($tools), 'memory_cache_hit' => $memory['cache_hit'],
                'estimated_input_tokens' => $estimated, 'estimation_method' => 'characters_divided_by_3',
                'target_input_tokens' => $policy->tokenBudget, 'target_exceeded' => $estimated > $policy->tokenBudget,
            ],
        ];
    }

    private function message(AiChatMessage $message): LlmMessage
    {
        if ($message->role === 'assistant' && $this->memory->isArtifact($message->content)) {
            // Artifact contents are data references in a new context segment, never
            // a partially replayed assistant transcript. The coder receives the exact source.
            return new LlmMessage('user', '<artifact_reference>'.json_encode([
                'message_id' => $message->id, 'kind' => 'assistant_artifact',
                'notice' => 'Sumber lengkap tersedia untuk delegasi/retrieval. Jangan menebak isi artefak dari referensi ini.',
            ], JSON_THROW_ON_ERROR).'</artifact_reference>');
        }

        return new LlmMessage($message->role, $message->content, reasoningContent: $message->reasoning_content);
    }

    private function length(Collection $messages): int
    {
        return $messages->sum(function ($source): int {
            $message = $this->message($source);

            return mb_strlen($message->content ?? '') + mb_strlen($message->reasoningContent ?? '');
        });
    }

    private function evidence(array $entries, Collection $completed, Collection $selected, array $terms, EcoContextPolicy $policy): array
    {
        $budget = $policy->memoryTokenBudget * 3;
        $data = ['kind' => 'untrusted_historical_evidence', 'quotes' => [], 'constraints' => [], 'tool_facts' => []];
        // Retain verbatim constraint lines, including those beyond an excerpt's head/tail.
        foreach ($completed->where('role', 'user')->reverse() as $message) {
            if ($selected->has($message->id) || $this->relevance->score($message->content, $terms) === 0) {
                continue;
            }
            foreach (preg_split('/\R/u', $message->content) ?: [] as $line) {
                if (preg_match('/\b(jangan|harus|wajib|pastikan|tidak boleh|gunakan|pakai|must|never|required|constraint)\b/iu', $line) !== 1) {
                    continue;
                }
                $candidate = $data;
                $candidate['constraints'][] = ['message_id' => $message->id, 'quote' => $line];
                if ($this->fits($candidate, $budget)) {
                    $data = $candidate;
                }
            }
        }
        $entries = collect($entries)->reject(fn ($entry) => $selected->has($entry['message_id']))
            ->filter(fn ($entry) => $this->relevance->score($entry['quote'], $terms) > 0)
            ->sortByDesc(fn ($entry) => $this->relevance->score($entry['quote'], $terms));
        foreach ($entries as $entry) {
            $candidate = $data;
            unset($entry['source_hash']);
            $candidate['quotes'][] = $entry;
            if ($this->fits($candidate, $budget)) {
                $data = $candidate;
            }
        }
        $assistantIds = $selected->where('role', 'assistant')->pluck('id');
        if ($assistantIds->isNotEmpty()) {
            $steps = AiChatRunStep::query()->where('kind', 'tool_call')->where('status', 'completed')->whereNotNull('private_payload')
                ->whereHas('run', fn ($query) => $query->where('status', 'completed')->where('session_id', $completed->first()->session_id)
                    ->whereIn('assistant_message_id', $assistantIds))->latest('id')->limit(4)->get();
            foreach ($steps as $step) {
                $result = $step->private_payload;
                unset($result['design_review']);
                if ($step->tool_name === AiScienceDelegation::TOOL_NAME) {
                    $original = $result['science_request']['original_problem'] ?? null;
                    $alreadyIncluded = is_string($original) && $selected->contains(
                        fn (AiChatMessage $message): bool => $message->role === 'user' && $message->content === $original
                    );
                    $result = $this->science->toolResult($result, $alreadyIncluded);
                }
                $candidate = $data;
                $candidate['tool_facts'][] = ['tool' => $step->tool_name, 'recorded_at' => $step->created_at?->toIso8601String(), 'result' => $result];
                if ($this->fits($candidate, $budget)) {
                    $data = $candidate;
                }
            }
        }

        return $data['quotes'] === [] && $data['constraints'] === [] && $data['tool_facts'] === [] ? [] : $data;
    }

    private function fits(array $value, int $budget): bool
    {
        return mb_strlen(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) <= $budget;
    }
}
