{{-- resources/views/admin/broadcasts/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Broadcast Afiliasi')
@section('page_description', 'Kirim postingan/notifikasi ke afiliasi yang menjadi kewenangan admin.')

@section('page_actions')
    <a href="{{ route('admin.broadcasts.create') }}"
       class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
        + Broadcast Baru
    </a>
@endsection

@section('content')
@php
    $filters = $filters ?? ['q' => '', 'status' => '', 'target' => ''];
    $summary = $summary ?? ['total' => 0, 'draft' => 0, 'published' => 0, 'archived' => 0];
@endphp
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Total</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-200">Draft</p>
            <p class="mt-2 text-3xl font-bold text-amber-800 dark:text-amber-100">{{ $summary['draft'] }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Published</p>
            <p class="mt-2 text-3xl font-bold text-emerald-800 dark:text-emerald-100">{{ $summary['published'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Archived</p>
            <p class="mt-2 text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $summary['archived'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('admin.broadcasts.index') }}" class="grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Judul, isi, atau pembuat"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                    <option value="published" @selected($filters['status'] === 'published')>Published</option>
                    <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Target</label>
                <input type="text"
                       name="target"
                       value="{{ $filters['target'] }}"
                       placeholder="Nama afiliasi"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div class="md:col-span-4 flex items-center justify-end gap-2">
                <a href="{{ route('admin.broadcasts.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left dark:border-slate-800">
                        <th class="py-3 pr-4">Judul</th>
                        <th class="py-3 pr-4">Target</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">Push</th>
                        <th class="py-3 pr-4">Publikasi</th>
                        <th class="py-3 pl-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($broadcasts as $broadcast)
                        <tr>
                            <td class="py-3 pr-4">
                                <div class="space-y-1">
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $broadcast->title }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Oleh {{ $broadcast->creator?->name ?: ($broadcast->creator?->email ?: 'Unknown') }}
                                    </p>
                                </div>
                            </td>
                            <td class="py-3 pr-4">
                                @if ($broadcast->target_mode === \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL)
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Global</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($broadcast->targets as $target)
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                {{ $target->affiliation_name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if ($broadcast->status === 'published')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Published</span>
                                @elseif ($broadcast->status === 'archived')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">Archived</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Draft</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                @if ($broadcast->send_push)
                                    <span class="text-xs">Success: {{ (int) $broadcast->push_success_count }}</span>
                                    <br>
                                    <span class="text-xs">Failed: {{ (int) $broadcast->push_failed_count }}</span>
                                @else
                                    <span class="text-xs text-slate-400">Nonaktif</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                {{ optional($broadcast->published_at)->format('Y-m-d H:i') ?: '-' }}
                            </td>
                            <td class="py-3 pl-4">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('admin.broadcasts.show', $broadcast) }}"
                                       class="rounded-lg bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-100 dark:hover:bg-indigo-500/20">
                                        Detail
                                    </a>

                                    @if ($broadcast->status === 'draft')
                                        <a href="{{ route('admin.broadcasts.edit', $broadcast) }}"
                                           class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.broadcasts.publish', $broadcast) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-100 dark:hover:bg-emerald-500/20">
                                                Publish
                                            </button>
                                        </form>
                                    @endif

                                    @if ($broadcast->status !== 'archived')
                                        <form method="POST" action="{{ route('admin.broadcasts.archive', $broadcast) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-100 dark:hover:bg-amber-500/20">
                                                Arsipkan
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.broadcasts.destroy', $broadcast) }}" onsubmit="return confirm('Hapus broadcast ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-100 dark:hover:bg-rose-500/20">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada broadcast.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $broadcasts->links() }}
        </div>
    </div>
</div>
@endsection
