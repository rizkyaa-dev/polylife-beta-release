{{-- resources/views/endmin/affiliations/manage/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Manage Afiliasi')
@section('page_description', 'Kelola master afiliasi sebagai sumber utama data afiliasi.')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Master Afiliasi</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">Manage Afiliasi</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tambah, edit, atau nonaktifkan afiliasi yang menjadi template utama.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('endmin.affiliations.index') }}"
               class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                Kembali
            </a>
            <a href="{{ route('endmin.affiliations.manage.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                Tambah Afiliasi
            </a>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('endmin.affiliations.manage.index') }}" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_12rem_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama afiliasi atau tipe"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="" @selected($filters['status'] === '')>Semua</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kualitas</label>
                <select name="quality" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="" @selected(($filters['quality'] ?? '') === '')>Semua</option>
                    <option value="ghost" @selected(($filters['quality'] ?? '') === 'ghost')>Hantu</option>
                    <option value="duplicate" @selected(($filters['quality'] ?? '') === 'duplicate')>Duplikat</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <a href="{{ route('endmin.affiliations.manage.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-[820px] w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <th class="py-3 pr-4">Tipe</th>
                        <th class="py-3 pr-4">Nama Afiliasi</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">User</th>
                        <th class="py-3 pr-4">Admin Assignment</th>
                        <th class="py-3 pr-4">Target Broadcast</th>
                        <th class="py-3 pr-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($templates as $template)
                        <tr>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $template->affiliation_type ?: '-' }}</td>
                            <td class="py-3 pr-4">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $template->affiliation_name }}</p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if (in_array(($template->affiliation_type ?: '__NULL__').'|'.($template->normalized_name ?: ''), $duplicateKeys ?? [], true))
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Duplikat potensial</span>
                                    @endif
                                    @if (! $template->is_active && $template->merged_into_id)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Merged</span>
                                    @endif
                                    @if ($template->users_count + $template->admin_assignments_count + $template->broadcast_targets_count === 0)
                                        <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-100">Kosong</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 pr-4">
                                @if ($template->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Aktif</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Nonaktif</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ (int) $template->users_count }}</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ (int) $template->admin_assignments_count }}</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ (int) $template->broadcast_targets_count }}</td>
                            <td class="py-3 pr-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('endmin.affiliations.manage.merge', $template) }}"
                                       class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:border-amber-300 hover:bg-amber-50 dark:border-amber-500/30 dark:text-amber-200 dark:hover:bg-amber-500/10">
                                        Merge
                                    </a>
                                    <a href="{{ route('endmin.affiliations.manage.edit', $template) }}"
                                       class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-200 dark:hover:bg-indigo-500/10">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada template afiliasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $templates->links() }}
        </div>
    </div>
</div>
@endsection
