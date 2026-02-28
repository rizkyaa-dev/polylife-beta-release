{{-- resources/views/admin/broadcasts/show.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Detail Broadcast')
@section('page_description', 'Lihat konten, target, dan hasil pengiriman push.')

@section('page_actions')
    <a href="{{ route('admin.broadcasts.index') }}"
       class="inline-flex items-center justify-center rounded-2xl border border-slate-200/80 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-indigo-200">
        Kembali
    </a>
@endsection

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $broadcast->title }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Dibuat oleh {{ $broadcast->creator?->name ?: ($broadcast->creator?->email ?: 'Unknown') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($broadcast->status === 'published')
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Published</span>
                @elseif ($broadcast->status === 'archived')
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">Archived</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Draft</span>
                @endif

                @if ($broadcast->isDraft())
                    <a href="{{ route('admin.broadcasts.edit', $broadcast) }}"
                       class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        Edit Draft
                    </a>
                    <form method="POST" action="{{ route('admin.broadcasts.publish', $broadcast) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                            Publish
                        </button>
                    </form>
                @endif

                @if (! $broadcast->isArchived())
                    <form method="POST" action="{{ route('admin.broadcasts.archive', $broadcast) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-100 dark:hover:bg-amber-500/20">
                            Arsipkan
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Target</p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ $broadcast->target_mode === \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL ? 'Global' : 'Afiliasi' }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Publish</p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ optional($broadcast->published_at)->format('Y-m-d H:i') ?: '-' }}</p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <p class="text-xs uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Push Success</p>
                <p class="mt-1 text-sm font-semibold text-emerald-800 dark:text-emerald-100">{{ (int) $broadcast->push_success_count }}</p>
            </div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/70 p-3 dark:border-rose-500/20 dark:bg-rose-500/10">
                <p class="text-xs uppercase tracking-wide text-rose-600 dark:text-rose-200">Push Failed</p>
                <p class="mt-1 text-sm font-semibold text-rose-800 dark:text-rose-100">{{ (int) $broadcast->push_failed_count }}</p>
            </div>
        </div>

        <div class="mt-4">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Target Afiliasi</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($broadcast->target_mode === \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL)
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Semua User Aktif</span>
                @else
                    @forelse ($broadcast->targets as $target)
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ $target->affiliation_name }}
                        </span>
                    @empty
                        <span class="text-sm text-slate-500 dark:text-slate-400">Belum ada target tersimpan.</span>
                    @endforelse
                @endif
            </div>
        </div>

        @if ($broadcast->image_path)
            <div class="mt-4">
                <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Gambar</p>
                <img src="{{ $broadcast->image_url }}"
                     alt="Gambar broadcast"
                     class="max-h-96 w-full rounded-xl border border-slate-200 object-cover dark:border-slate-700">
            </div>
        @endif

        <div class="mt-4">
            <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Isi Pesan</p>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 whitespace-pre-line text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200">{{ $broadcast->body }}</div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Log Push Terbaru</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left dark:border-slate-800">
                        <th class="py-3 pr-4">Waktu</th>
                        <th class="py-3 pr-4">User</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">Endpoint</th>
                        <th class="py-3 pr-4">Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($pushLogs as $log)
                        <tr>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                {{ $log->user?->name ?: ($log->user?->email ?: '-') }}
                            </td>
                            <td class="py-3 pr-4">
                                @if ($log->status === 'sent')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Sent</span>
                                @elseif ($log->status === 'expired')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Expired</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-100">Failed</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-500 dark:text-slate-400 break-all">{{ $log->endpoint ?: '-' }}</td>
                            <td class="py-3 pr-4 text-xs text-rose-600 dark:text-rose-300">{{ $log->error_message ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada log push.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $pushLogs->links() }}
        </div>
    </div>
</div>
@endsection
