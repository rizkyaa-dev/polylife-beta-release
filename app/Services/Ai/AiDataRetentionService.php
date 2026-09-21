<?php

namespace App\Services\Ai;

use App\Models\AiActionProposal;
use App\Models\AiChatMessage;
use App\Models\AiChatRunStep;
use App\Models\AiScienceExecution;

final class AiDataRetentionService
{
    /** @return array{expired_proposals: int, reasoning_redacted: int, tool_payloads_redacted: int, proposals_redacted: int, message_proposals_redacted: int} */
    public function prune(): array
    {
        $cutoff = now()->subDays(max(1, (int) config('services.ai_sensitive_data_retention_days', 30)));
        $expiredProposals = AiActionProposal::query()
            ->where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
        $reasoningRedacted = AiChatMessage::query()
            ->whereNotNull('reasoning_content')
            ->where('created_at', '<=', $cutoff)
            ->update(['reasoning_content' => null]);
        $toolPayloadsRedacted = AiChatRunStep::query()
            ->whereNotNull('private_payload')
            ->where('created_at', '<=', $cutoff)
            ->update(['private_payload' => null]);
        AiScienceExecution::query()->where('created_at', '<=', $cutoff)->whereNotNull('private_payload')
            ->whereIn('status', ['completed', 'cancelled', 'failed'])->update(['private_payload' => null]);
        $proposalsRedacted = AiActionProposal::query()
            ->where('status', '!=', 'pending')
            ->where('updated_at', '<=', $cutoff)
            ->update([
                'payload_json' => '[]',
                'signature' => str_repeat('0', 64),
            ]);
        $messageProposalsRedacted = $this->redactPersistedMessageProposals($cutoff);

        return [
            'expired_proposals' => $expiredProposals,
            'reasoning_redacted' => $reasoningRedacted,
            'tool_payloads_redacted' => $toolPayloadsRedacted,
            'proposals_redacted' => $proposalsRedacted,
            'message_proposals_redacted' => $messageProposalsRedacted,
        ];
    }

    private function redactPersistedMessageProposals(\DateTimeInterface $cutoff): int
    {
        $updated = 0;
        AiChatMessage::query()
            ->whereNotNull('tool_calls_json')
            ->where('created_at', '<=', $cutoff)
            ->select(['id', 'tool_calls_json'])
            ->chunkById(100, function ($messages) use (&$updated): void {
                foreach ($messages as $message) {
                    $proposals = array_map(static function (array $proposal): array {
                        unset($proposal['payload'], $proposal['signature']);

                        return $proposal;
                    }, (array) $message->tool_calls_json);
                    $message->update(['tool_calls_json' => $proposals]);
                    $updated++;
                }
            });

        return $updated;
    }
}
