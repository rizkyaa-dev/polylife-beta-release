<?php

namespace App\Services\Ai;

use App\Models\AiChatRun;
use App\Services\Ai\Science\Client\ClientComputationPresenter;

final class AiChatResponseFactory
{
    public function __construct(private readonly AiMarkdownRenderer $markdownRenderer) {}

    /** @return array<string, mixed> */
    public function completed(AiChatRun $run): array
    {
        $run->loadMissing(['session', 'branch', 'userMessage', 'assistantMessage', 'steps']);
        $userMessage = $run->userMessage;
        $assistantMessage = $run->assistantMessage;
        if (! $userMessage || ! $assistantMessage || ! $run->session || ! $run->branch) {
            throw new \LogicException('Run AI selesai tanpa hasil percakapan yang lengkap.');
        }

        $versions = $run->session->messages()
            ->where('revision_group_id', $userMessage->revision_group_id)
            ->where('role', 'user')
            ->where('status', 'completed')
            ->oldest('id')
            ->get();

        return [
            'status' => 'success',
            'session_id' => $run->session->id,
            'active_branch_id' => $run->branch->id,
            'reply' => $assistantMessage->content,
            'reply_html' => $this->markdownRenderer->render($assistantMessage->content),
            'proposals' => $assistantMessage->tool_calls_json ?? [],
            'user_message' => [
                'id' => $userMessage->id,
                'content' => $userMessage->content,
                'branch_id' => $userMessage->branch_id,
                'revision_index' => $versions->search(fn ($version) => $version->is($userMessage)) + 1,
                'revision_count' => $versions->count(),
                'versions' => $versions->map(fn ($version) => [
                    'message_id' => $version->id,
                    'branch_id' => $version->branch_id,
                ])->values(),
            ],
            'assistant_message' => [
                'id' => $assistantMessage->id,
                'content' => $assistantMessage->content,
                'html' => $this->markdownRenderer->render($assistantMessage->content),
            ],
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'duration_ms' => $run->duration_ms,
                'steps' => $run->steps->map(fn ($step) => [
                    'kind' => $step->kind,
                    'status' => $step->status,
                    'label' => $step->label,
                    'execution_mode' => $step->public_metadata['execution_mode'] ?? null,
                    'client_computation' => ClientComputationPresenter::details($step->private_payload),
                    'duration_ms' => $step->duration_ms,
                ])->values(),
            ],
        ];
    }
}
