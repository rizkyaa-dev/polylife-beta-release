{{-- resources/views/pengumuman/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Pengumuman Afiliasi')
@section('page_description', 'Postingan resmi dari admin afiliasi yang relevan untuk akun Anda.')

@section('content')
@php
    $filters = $filters ?? ['q' => ''];
@endphp
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('pengumuman.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari Pengumuman</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Judul, isi, atau nama admin"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('pengumuman.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Cari
                </button>
            </div>
        </form>
    </div>

    <div class="mx-auto max-w-2xl space-y-4" data-feed-container>
        @if ($broadcasts->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                Belum ada pengumuman untuk afiliasi Anda.
            </div>
        @else
            @include('pengumuman.partials.feed-items', ['broadcasts' => $broadcasts])
        @endif
    </div>

    @if ($broadcasts->isNotEmpty())
        <div class="mx-auto max-w-2xl space-y-3">
            <div class="hidden items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400" data-feed-loader>
                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-indigo-500"></span>
                Memuat pengumuman berikutnya...
            </div>
            <p class="hidden text-center text-xs text-rose-500" data-feed-error>
                Gagal memuat data baru. Scroll lagi untuk coba ulang.
            </p>
            @if ($broadcasts->nextPageUrl())
                <div class="h-6" data-feed-sentinel data-next-url="{{ $broadcasts->nextPageUrl() }}"></div>
            @endif
            <p class="hidden text-center text-xs text-slate-500 dark:text-slate-400" data-feed-finished>
                Semua pengumuman sudah ditampilkan.
            </p>
        </div>
        <noscript>
            <div class="mx-auto max-w-2xl">
                {{ $broadcasts->links() }}
            </div>
        </noscript>
    @endif
    <div class="mx-auto max-w-2xl">
        <p class="text-center text-xs text-slate-400">
            Scroll ke bawah untuk memuat postingan berikutnya.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const feed = document.querySelector('[data-feed-container]');
    const sentinel = document.querySelector('[data-feed-sentinel]');

    if (!feed || !sentinel) {
        return;
    }

    const loader = document.querySelector('[data-feed-loader]');
    const errorText = document.querySelector('[data-feed-error]');
    const finishedText = document.querySelector('[data-feed-finished]');

    let nextUrl = sentinel.dataset.nextUrl || '';
    let isLoading = false;
    let observer = null;

    const toggle = (element, hidden) => {
        if (!element) {
            return;
        }
        element.classList.toggle('hidden', hidden);
        if (element === loader) {
            element.classList.toggle('flex', !hidden);
        }
    };

    const finishFeed = () => {
        if (observer) {
            observer.disconnect();
        }
        sentinel.remove();
        toggle(loader, true);
        toggle(errorText, true);
        toggle(finishedText, false);
    };

    if (!nextUrl) {
        finishFeed();
        return;
    }

    const loadMore = async () => {
        if (isLoading || !nextUrl) {
            return;
        }

        isLoading = true;
        toggle(loader, false);
        toggle(errorText, true);

        try {
            const response = await fetch(nextUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch next feed page');
            }

            const payload = await response.json();
            const html = (payload.html || '').trim();

            if (html !== '') {
                feed.insertAdjacentHTML('beforeend', html);
            }

            nextUrl = payload.next_page_url || '';
            sentinel.dataset.nextUrl = nextUrl;

            if (!nextUrl) {
                finishFeed();
            }
        } catch (error) {
            toggle(errorText, false);
        } finally {
            isLoading = false;
            toggle(loader, true);
        }
    };

    observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                loadMore();
                break;
            }
        }
    }, {
        root: null,
        rootMargin: '700px 0px',
        threshold: 0,
    });

    observer.observe(sentinel);
})();
</script>
@endpush
