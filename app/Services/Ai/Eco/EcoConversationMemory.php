<?php

namespace App\Services\Ai\Eco;

use App\Models\AiChatBranch;
use App\Models\AiChatMessage;
use App\Models\AiChatRun;
use App\Services\Ai\AiConversationBranchService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EcoConversationMemory
{
    private const VERSION = 1;

    private const MAX_ENTRIES = 48;

    public function __construct(private readonly AiConversationBranchService $branches) {}

    /** @return array{entries: array, cache_hit: bool} */
    public function read(Collection $messages, AiChatBranch $branch): array
    {
        $completed = $messages->where('status', 'completed')->values();
        $sources = $completed->keyBy('id');
        try {
            $cached = $branch->eco_context_memory;
        } catch (DecryptException $exception) {
            report($exception);
            $cached = null;
        }
        $cached = is_array($cached) ? $cached : [];
        $through = $sources->get(is_int($cached['through_message_id'] ?? null) ? $cached['through_message_id'] : null);
        $valid = ($cached['version'] ?? null) === self::VERSION
            && is_array($cached['entries'] ?? null)
            && count($cached['entries']) <= self::MAX_ENTRIES
            && $through !== null
            && is_string($cached['through_hash'] ?? null)
            && $branch->eco_memory_refreshed_at?->greaterThan(now()->subDays(max(1, (int) config('services.ai_sensitive_data_retention_days', 30))))
            && hash_equals($cached['through_hash'], hash('sha256', $through->content));
        if ($valid) {
            foreach ($cached['entries'] as $entry) {
                $source = is_array($entry) && is_int($entry['message_id'] ?? null) ? $sources->get($entry['message_id']) : null;
                if (! $source || ! is_string($entry['quote'] ?? null) || mb_strlen($entry['quote']) > 900
                    || ($entry['role'] ?? null) !== $source->role || ! is_bool($entry['shortened'] ?? null)
                    || ! in_array($entry['kind'] ?? null, ['artifact_reference', 'conversation_quote'], true)
                    || ! is_string($entry['source_hash'] ?? null)
                    || ! hash_equals($entry['source_hash'], hash('sha256', $source->content))) {
                    $valid = false;
                    break;
                }
            }
        }
        $entries = $valid ? $cached['entries'] : [];
        $delta = $valid ? $completed->skipUntil(fn ($message) => $message->id === $through->id)->skip(1) : $completed;
        foreach ($delta as $message) {
            $entries[] = $this->entry($message);
        }

        return ['entries' => array_slice($entries, -self::MAX_ENTRIES), 'cache_hit' => $valid];
    }

    public function refresh(int $runId): void
    {
        $run = AiChatRun::query()->where('status', 'completed')->with(['session', 'branch', 'assistantMessage'])->find($runId);
        if (! $run?->branch || ! $run->assistantMessage || ! $run->session
            || $run->response_mode !== 'eco'
            || EcoContextPolicy::fromArray($run->context_policy) === null
            || $run->branch->head_message_id !== $run->assistant_message_id) {
            return;
        }
        $messages = $this->branches->recentLineage($run->session, $run->assistant_message_id);
        $memory = $this->read($messages, $run->branch);
        $snapshot = [
            'version' => self::VERSION,
            'through_message_id' => $run->assistant_message_id,
            'through_hash' => hash('sha256', $run->assistantMessage->content),
            'entries' => $memory['entries'],
        ];
        $encoded = encrypt(json_encode($snapshot, JSON_THROW_ON_ERROR), false);
        // A delayed job cannot publish a snapshot over a newer turn or another branch.
        DB::table('ai_chat_branches')->where('id', $run->branch_id)
            ->where('session_id', $run->session_id)->where('head_message_id', $run->assistant_message_id)
            ->update(['eco_context_memory' => $encoded, 'eco_memory_refreshed_at' => now()]);
    }

    public function isArtifact(string $content): bool
    {
        return str_contains($content, '```') || str_contains($content, '~~~');
    }

    private function entry(AiChatMessage $message): array
    {
        $artifact = $message->role === 'assistant' && $this->isArtifact($message->content);
        $text = trim((string) $message->content);
        $shortened = mb_strlen($text) > 800;

        return [
            'message_id' => $message->id,
            'role' => $message->role,
            'kind' => $artifact ? 'artifact_reference' : 'conversation_quote',
            'source_hash' => hash('sha256', $message->content),
            'quote' => $artifact ? 'Artefak tersedia melalui ID pesan ini; ambil sumber lengkap sebelum revisi.'
                : ($shortened ? mb_substr($text, 0, 400)."\n[…]\n".mb_substr($text, -400) : $text),
            'shortened' => $shortened || $artifact,
        ];
    }
}
