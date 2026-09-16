<nav class="ai-sidebar-nav" aria-label="Percakapan AI" data-ai-sidebar data-ai-session-base-url="{{ route('ai.workspace') }}/sessions">
    <a class="ai-sidebar-action ai-new-chat" href="{{ route('ai.workspace', ['new' => 1]) }}" title="Percakapan baru" aria-label="Percakapan baru">
        <x-ai.icon name="plus" /><span class="sidebar-link-text">Percakapan baru</span>
    </a>
    <div class="ai-history-content">
        <label class="ai-history-search">
            <x-ai.icon name="search" />
            <span class="sr-only">Cari di riwayat terbaru</span>
            <input type="search" placeholder="Cari percakapan terbaru" data-ai-history-search autocomplete="off">
        </label>
        <h2 class="ai-history-heading">Percakapan terbaru</h2>
        <div class="ai-history-list" data-ai-history-list>
            @foreach ($sessions as $session)
                <x-ai.history-item :session="$session" :active="$currentSession?->id === $session->id" />
            @endforeach
        </div>
        <p class="ai-history-empty" data-ai-history-empty @if ($sessions->isNotEmpty()) hidden @endif>Belum ada percakapan. Mulai dengan pesan pertamamu.</p>
        <p class="ai-history-empty" data-ai-history-no-results hidden role="status">Tidak ada judul yang cocok di riwayat terbaru.</p>
        <p class="ai-history-action-status" data-ai-history-action-status hidden role="status"></p>
    </div>
</nav>
<template data-ai-history-item-template>
    <x-ai.history-item />
</template>
<div class="ai-history-menu" data-ai-history-menu hidden aria-label="Aksi percakapan">
    <button type="button" data-ai-history-action="rename"><x-ai.icon name="rename" /><span>Ubah nama</span></button>
    <button type="button" class="ai-history-menu-danger" data-ai-history-action="delete"><x-ai.icon name="trash" /><span>Hapus</span></button>
</div>
