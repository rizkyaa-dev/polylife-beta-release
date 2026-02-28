{{-- resources/views/endmin/broadcasts/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Verifikasi Broadcast')
@section('page_description', 'Pantau postingan admin dan lakukan moderasi jika ditemukan konten berbahaya.')

@php
    $stats = $stats ?? [
        'total' => 0,
        'published' => 0,
        'archived' => 0,
        'draft' => 0,
    ];

    $filters = $filters ?? [
        'q' => '',
        'status' => '',
        'target_mode' => '',
    ];
@endphp

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Total Broadcast</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Published</p>
            <p class="mt-2 text-3xl font-bold text-emerald-800 dark:text-emerald-100">{{ $stats['published'] }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-200">Draft</p>
            <p class="mt-2 text-3xl font-bold text-amber-800 dark:text-amber-100">{{ $stats['draft'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Archived</p>
            <p class="mt-2 text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['archived'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('endmin.broadcast-verifications.index') }}" class="grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Judul, isi, pembuat, atau afiliasi target"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="published" @selected($filters['status'] === 'published')>Published</option>
                    <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                    <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Target Mode</label>
                <select name="target_mode" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="affiliation" @selected($filters['target_mode'] === 'affiliation')>Afiliasi</option>
                    <option value="global" @selected($filters['target_mode'] === 'global')>Global</option>
                </select>
            </div>
            <div class="md:col-span-4 flex items-center justify-end gap-2">
                <a href="{{ route('endmin.broadcast-verifications.index') }}"
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
                        <th class="py-3 pr-4">Judul & Isi</th>
                        <th class="py-3 pr-4">Pembuat</th>
                        <th class="py-3 pr-4">Target</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">Publikasi</th>
                        <th class="py-3 pl-4 text-right">Aksi Moderasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($broadcasts as $broadcast)
                        <tr>
                            <td class="py-3 pr-4 align-top">
                                <div class="flex items-start gap-3">
                                    @if ($broadcast->image_path)
                                        <img src="{{ $broadcast->image_url }}"
                                             alt="Preview broadcast"
                                             class="h-16 w-24 shrink-0 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $broadcast->title }}</p>
                                        <p class="mt-1 line-clamp-3 whitespace-pre-wrap text-xs text-slate-500 dark:text-slate-400">{{ $broadcast->body }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 pr-4 align-top">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $broadcast->creator?->name ?: '-' }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 break-all">{{ $broadcast->creator?->email ?: '-' }}</p>
                                <p class="mt-1">
                                    @if ($broadcast->creator?->isSuperAdmin())
                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Super Admin</span>
                                    @elseif ($broadcast->creator?->isAdminOnly())
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-100">Admin</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Pengguna</span>
                                    @endif
                                </p>
                            </td>
                            <td class="py-3 pr-4 align-top">
                                @if ($broadcast->target_mode === \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL)
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Global</span>
                                @else
                                    <div class="flex max-w-xs flex-wrap gap-1">
                                        @foreach ($broadcast->targets->take(3) as $target)
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                {{ $target->affiliation_name }}
                                            </span>
                                        @endforeach
                                        @if ($broadcast->targets->count() > 3)
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                +{{ $broadcast->targets->count() - 3 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 pr-4 align-top">
                                @if ($broadcast->status === 'published')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Published</span>
                                @elseif ($broadcast->status === 'archived')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">Archived</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Draft</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 align-top text-xs text-slate-600 dark:text-slate-300">
                                {{ optional($broadcast->published_at)->format('Y-m-d H:i') ?: '-' }}
                            </td>
                            <td class="py-3 pl-4 align-top">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('endmin.broadcast-verifications.show', $broadcast) }}"
                                       class="rounded-lg bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-100 dark:hover:bg-indigo-500/20">
                                        Detail
                                    </a>
                                    @if (! $broadcast->isArchived())
                                        <form method="POST" action="{{ route('endmin.broadcast-verifications.archive', $broadcast) }}" onsubmit="return confirm('Arsipkan broadcast ini?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-100 dark:hover:bg-amber-500/20">
                                                Arsipkan
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('endmin.broadcast-verifications.unarchive', $broadcast) }}" onsubmit="return confirm('Kembalikan broadcast ini dari arsip?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-100 dark:hover:bg-emerald-500/20">
                                                Unarchive
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('endmin.broadcast-verifications.destroy', $broadcast) }}" onsubmit="return confirm('Hapus broadcast ini secara permanen?')">
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
                            <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada broadcast untuk dimoderasi.</td>
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
