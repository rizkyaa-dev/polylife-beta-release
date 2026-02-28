@foreach ($broadcasts as $broadcast)
    <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-100">
                {{ strtoupper(mb_substr($broadcast->creator?->name ?: 'A', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ $broadcast->creator?->name ?: ($broadcast->creator?->email ?: 'Admin') }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ optional($broadcast->published_at)->format('d M Y H:i') }}
                </p>
            </div>
            @if ($broadcast->target_mode === \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL)
                <span class="ml-auto inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">
                    Global
                </span>
            @endif
        </div>

        @if ($broadcast->image_path)
            <img src="{{ $broadcast->image_url }}"
                 alt="Gambar pengumuman"
                 class="mx-auto my-3 block max-h-[42vh] w-auto max-w-[82%] sm:max-w-[78%] lg:max-h-[46vh]">
        @endif

        <div class="space-y-3 px-5 py-4">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $broadcast->title }}</h3>
            <div class="line-clamp-6 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $broadcast->body }}</div>
            <a href="{{ route('pengumuman.show', $broadcast) }}"
               class="inline-flex items-center rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-100 dark:hover:bg-indigo-500/20">
                Buka Detail
            </a>
        </div>
    </article>
@endforeach
