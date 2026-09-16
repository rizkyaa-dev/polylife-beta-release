@extends('layouts.app')

@section('workspace_canvas')
@php
    $hasMessages = $currentSession && ($currentSession->messages->isNotEmpty() || $activeRun);
    $displayName = auth()->user()->profile?->display_name ?: auth()->user()->name;
    $proposalStates = $currentSession?->proposals->keyBy('action_id') ?? collect();
    $activeThinkingEffort = $assistant->thinking_effort ?? \App\Services\Ai\Enums\ThinkingEffort::High;
@endphp
<main class="ai-workspace {{ $hasMessages ? 'has-messages' : '' }}" data-ai-workspace
    data-session-id="{{ $currentSession?->id }}"
    data-active-run-id="{{ $activeRun?->id }}"
    data-active-run-prompt="{{ $activeRun?->userMessage?->content }}"
    data-active-run-message-id="{{ $activeRun?->user_message_id }}"
    data-chat-url="{{ route('ai.chat') }}"
    data-run-url-template="{{ route('ai.runs.show', ['run' => '__RUN__']) }}"
    data-cancel-run-url-template="{{ route('ai.runs.cancel', ['run' => '__RUN__']) }}"
    data-edit-url-template="{{ route('ai.messages.edit', ['message' => '__MESSAGE__']) }}"
    data-activate-branch-url-template="{{ route('ai.branches.activate', ['branch' => '__BRANCH__']) }}"
    data-messages-url-template="{{ route('ai.sessions.messages', ['session' => '__SESSION__']) }}"
    data-thinking-url="{{ route('ai.settings.thinking.update') }}"
    data-workspace-url="{{ route('ai.workspace') }}"
    data-confirm-url="{{ route('ai.action.confirm') }}"
    data-reject-url="{{ route('ai.action.reject') }}">
    <header class="ai-topbar">
        <div class="ai-topbar-heading">
            <button type="button" class="ai-button ai-button-quiet mobile-nav-trigger" data-mobile-sidebar-open aria-label="Buka menu percakapan"><x-ai.icon name="menu" /><span>Menu</span></button>
            <div class="ai-assistant-title">
                <span class="ai-assistant-name">{{ $assistant->assistant_name }}</span>
                <span class="ai-assistant-tone">{{ $assistant->personaLabel() }}</span>
            </div>
        </div>
        <button type="button" class="ai-icon-button" data-ai-settings-open aria-label="Pengaturan asisten" title="Pengaturan asisten"><x-ai.icon name="settings" /></button>
    </header>
    @if (session('success'))
        <p class="ai-notice" role="status">{{ session('success') }}</p>
    @endif
    <div class="ai-chat-stage">
        <section class="ai-welcome" data-ai-welcome @if ($hasMessages) hidden @endif aria-labelledby="ai-greeting">
            <div class="ai-welcome-mark"><x-ai.icon name="spark" /></div>
            <h1 id="ai-greeting">Halo, <span>{{ $displayName }}.</span><br>Apa yang ingin kamu bereskan?</h1>
            <p>Dari jadwal kuliah sampai pengeluaran harian,<br class="ai-desktop-break"> kita mulai dari mana?</p>
        </section>
        <section class="ai-messages" data-ai-messages aria-label="Isi percakapan" role="log" aria-live="polite" aria-relevant="additions" tabindex="0" @unless ($hasMessages) hidden @endunless>
            <div class="ai-messages-inner" data-ai-message-list>
                @if ($hasEarlierMessages)
                    <button type="button" class="ai-load-earlier" data-ai-load-earlier>Muat percakapan sebelumnya</button>
                @endif
                @foreach ($currentSession?->messages ?? [] as $message)
                    @if (in_array($message->role, ['user', 'assistant']))
                        @include('ai.partials.message', ['message' => $message])
                    @endif
                @endforeach
                @if ($activeRun?->userMessage)
                    @include('ai.partials.message', ['message' => $activeRun->userMessage])
                    <article class="ai-message ai-message-assistant ai-thinking-indicator" data-ai-thinking-indicator aria-label="Asisten sedang memproses permintaan" aria-live="polite">
                        <div class="ai-message-mark"><x-ai.icon name="spark" /></div>
                        <div class="ai-message-body"><span data-ai-thinking-copy>Memproses permintaan</span><span class="ai-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span></div>
                    </article>
                @endif
            </div>
        </section>
        <div class="ai-compose-area">
            <div class="ai-request-error" data-ai-error hidden role="alert"><span data-ai-error-text></span><button type="button" data-ai-retry>Coba lagi</button></div>
            @include('ai.partials.composer')
            <div class="ai-suggestions" data-ai-suggestions @if ($hasMessages) hidden @endif aria-label="Ide percakapan">
                <button type="button" data-ai-prompt="Apa jadwal kuliah saya minggu ini?"><x-ai.icon name="calendar" />Jadwal minggu ini</button>
                <button type="button" data-ai-prompt="Berapa sisa anggaran dan total pengeluaran bulan ini?"><x-ai.icon name="wallet" />Cek pengeluaran</button>
                <button type="button" data-ai-prompt="Tampilkan tugas dan to-do yang belum selesai."><x-ai.icon name="todo" />Tugas yang tertunda</button>
            </div>
            <p class="ai-composer-note">Periksa kembali jawaban AI. Perubahan data selalu menunggu konfirmasimu.</p>
        </div>
    </div>
    @include('ai.partials.settings')
    <template data-ai-user-template>@include('ai.partials.message', ['message' => (object) ['id' => null, 'branch_id' => null, 'role' => 'user', 'content' => '', 'tool_calls_json' => [], 'revisions' => collect()]])</template>
    <template data-ai-assistant-template>@include('ai.partials.message', ['message' => (object) ['id' => null, 'branch_id' => null, 'role' => 'assistant', 'content' => '', 'tool_calls_json' => [], 'run' => null]])</template>
    <template data-ai-thinking-template>
        <article class="ai-message ai-message-assistant ai-thinking-indicator" data-ai-thinking-indicator aria-label="Asisten sedang memproses permintaan" aria-live="polite">
            <div class="ai-message-mark"><x-ai.icon name="spark" /></div>
            <div class="ai-message-body"><span data-ai-thinking-copy>Memproses permintaan</span><span class="ai-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span></div>
        </article>
    </template>
    <template data-ai-tool-icon-template><x-ai.icon name="tool" /></template>
    <template data-ai-proposal-template>@include('ai.partials.proposal', ['proposal' => [], 'state' => null])</template>
    @include('ai.partials.code-preview')
</main>
@endsection
