@php
    $isUser = $message->role === 'user';
    $revisions = $isUser ? collect($message->revisions ?? []) : collect();
    $revisionIndex = $revisions->search(fn ($revision) => (int) $revision->id === (int) ($message->id ?? 0));
    $revisionIndex = $revisionIndex === false ? 0 : $revisionIndex;
    $previousRevision = $revisionIndex > 0 ? $revisions->get($revisionIndex - 1) : null;
    $nextRevision = $revisionIndex < $revisions->count() - 1 ? $revisions->get($revisionIndex + 1) : null;
    $run = $isUser ? null : ($message->run ?? null);
    $hasProcess = $run && $run->steps->isNotEmpty();
@endphp
<article class="ai-message ai-message-{{ $message->role }}"
    @if ($message->id ?? null) data-message-id="{{ $message->id }}" @endif
    @if ($message->branch_id ?? null) data-branch-id="{{ $message->branch_id }}" @endif
    aria-label="{{ $isUser ? 'Pesan kamu' : 'Jawaban asisten' }}">
    @unless ($isUser)
        <div class="ai-message-mark {{ $hasProcess ? 'ai-message-mark-process' : '' }}"><x-ai.icon name="spark" /></div>
    @endunless
    <div class="ai-message-body">
        @unless ($isUser)
            @if ($hasProcess)
                <details class="ai-process" data-ai-process>
                    <summary><span>Proses AI · {{ max(1, (int) ceil(($run->duration_ms ?? 0) / 1000)) }} dtk</span><x-ai.icon name="chevron-down" /></summary>
                    <ol class="ai-process-steps">
                        @foreach ($run->steps as $step)
                            <li class="ai-process-step ai-process-step-{{ $step->kind }}" data-status="{{ $step->status }}">
                                <span class="ai-process-step-mark">@if ($step->kind === 'tool_call')<x-ai.icon name="tool" />@endif</span>
                                <span>
                                    <strong>{{ $step->label }}</strong>
                                    <small>{{ $step->kind === 'tool_call' ? 'Aktivitas alat' : 'Reasoning ringkas' }}@if ($step->duration_ms !== null) · {{ max(1, (int) round($step->duration_ms / 1000)) }} dtk @endif · {{ $step->status === 'failed' ? 'Gagal' : 'Selesai' }}</small>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif
            <div class="ai-message-text" data-message-text>{!! app(\App\Services\Ai\AiMarkdownRenderer::class)->render($message->content) !!}</div>
        @else
            <div class="ai-user-message-content" data-ai-user-content>
                <div class="ai-message-text" data-message-text>{{ $message->content }}</div>
                <form class="ai-message-edit-form" data-ai-edit-form hidden>
                    <label class="sr-only" for="ai-edit-{{ $message->id ?? 'template' }}">Edit pesan</label>
                    <textarea id="ai-edit-{{ $message->id ?? 'template' }}" maxlength="2000" rows="2" data-ai-edit-input>{{ $message->content }}</textarea>
                    <p class="ai-message-edit-error" data-ai-edit-error hidden role="alert"></p>
                    <div class="ai-message-edit-actions">
                        <button type="button" class="ai-button ai-button-quiet" data-ai-edit-cancel>Batal</button>
                        <button type="submit" class="ai-button ai-button-primary" data-ai-edit-save>Simpan & kirim ulang</button>
                    </div>
                </form>
            </div>
            <div class="ai-message-actions" data-ai-message-actions>
                <button type="button" class="ai-message-action" data-ai-edit-open aria-label="Edit pesan"><x-ai.icon name="rename" /></button>
                <div class="ai-version-nav" data-ai-version-nav @if ($revisions->count() <= 1) hidden @endif>
                    <button type="button" data-ai-version-branch="{{ $previousRevision?->branch_id }}" aria-label="Tampilkan versi sebelumnya" @disabled(! $previousRevision)><x-ai.icon name="chevron-left" /></button>
                    <span data-ai-version-counter>{{ $revisions->isEmpty() ? '1 / 1' : ($revisionIndex + 1).' / '.$revisions->count() }}</span>
                    <button type="button" data-ai-version-branch="{{ $nextRevision?->branch_id }}" aria-label="Tampilkan versi berikutnya" @disabled(! $nextRevision)><x-ai.icon name="chevron-right" /></button>
                </div>
            </div>
        @endunless
        @unless ($isUser)
            <div class="ai-proposals" data-message-proposals>
                @foreach ($message->tool_calls_json ?? [] as $proposal)
                    @include('ai.partials.proposal', ['proposal' => $proposal, 'state' => $proposalStates->get($proposal['action_id'])])
                @endforeach
            </div>
        @endunless
    </div>
</article>
