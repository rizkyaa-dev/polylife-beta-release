@props(['session' => null, 'active' => false])
@php
    $sessionId = $session?->id;
    $title = $session?->title ?? '';
@endphp
<div class="ai-history-item" data-ai-history-item data-session-id="{{ $sessionId }}" data-session-title="{{ $title }}" data-active="{{ $active ? 'true' : 'false' }}">
    <a href="{{ $session ? route('ai.workspace', ['session' => $sessionId]) : '#' }}" class="ai-history-link" data-ai-history-link @if ($active) aria-current="page" @endif title="{{ $title }}">
        <x-ai.icon name="chat" />
        <span data-ai-history-title>{{ $title }}</span>
    </a>
    <button type="button" class="ai-history-menu-trigger" data-ai-history-menu-trigger aria-label="Opsi untuk {{ $title }}" aria-haspopup="true" aria-expanded="false" title="Opsi percakapan">
        <x-ai.icon name="more" />
    </button>
</div>
