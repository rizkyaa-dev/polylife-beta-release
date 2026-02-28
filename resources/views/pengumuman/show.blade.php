{{-- resources/views/pengumuman/show.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Detail Pengumuman')
@section('page_description', 'Informasi lengkap dari admin afiliasi.')

@section('page_actions')
    <a href="{{ route('pengumuman.index') }}"
       class="inline-flex items-center justify-center rounded-2xl border border-slate-200/80 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-indigo-200">
        Kembali
    </a>
@endsection

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <div class="xl:col-span-2 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ optional($broadcast->published_at)->format('d M Y H:i') }}</span>
            <span>•</span>
            <span>{{ $broadcast->creator?->name ?: ($broadcast->creator?->email ?: 'Admin') }}</span>
            @if ($broadcast->target_mode === \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL)
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Global</span>
            @endif
        </div>

        <h2 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $broadcast->title }}</h2>

        @if ($broadcast->image_path)
            <img src="{{ $broadcast->image_url }}"
                 alt="Gambar pengumuman"
                 class="mt-4 max-h-[420px] w-full rounded-xl object-cover">
        @endif

        <div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700 dark:text-slate-200">{{ $broadcast->body }}</div>
    </div>

    <aside class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Pengumuman Lainnya</h3>
        <div class="mt-3 space-y-3">
            @forelse ($relatedBroadcasts as $related)
                <a href="{{ route('pengumuman.show', $related) }}"
                   class="block rounded-xl border border-slate-200 px-3 py-2 transition hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-400/40">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $related->title }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($related->published_at)->format('d M Y H:i') }}</p>
                </a>
            @empty
                <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada pengumuman lain.</p>
            @endforelse
        </div>
    </aside>
</div>
@endsection
