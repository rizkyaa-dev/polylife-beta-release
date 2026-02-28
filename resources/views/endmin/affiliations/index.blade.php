{{-- resources/views/endmin/affiliations/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Master Afiliasi')
@section('page_description', 'Ringkasan afiliasi user untuk validasi operasional admin dan verifikasi.')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('endmin.affiliations.index') }}" class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama afiliasi atau tipe"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status User</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                    <option value="verified" @selected($filters['status'] === 'verified')>Verified</option>
                    <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                </select>
            </div>
            <div class="md:col-span-3 flex items-center justify-end gap-2">
                <a href="{{ route('endmin.affiliations.index') }}"
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
                        <th class="py-3 pr-4">Tipe</th>
                        <th class="py-3 pr-4">Nama Afiliasi</th>
                        <th class="py-3 pr-4">Total User</th>
                        <th class="py-3 pr-4">Verified</th>
                        <th class="py-3 pr-4">Pending</th>
                        <th class="py-3 pr-4">Admin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($affiliations as $affiliation)
                        <tr>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $affiliation->affiliation_type ?: '-' }}</td>
                            <td class="py-3 pr-4 font-medium text-slate-900 dark:text-slate-100">{{ $affiliation->affiliation_name }}</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ (int) $affiliation->total_users }}</td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">
                                    {{ (int) $affiliation->verified_users }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">
                                    {{ (int) $affiliation->pending_users }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-100">
                                    {{ (int) $affiliation->admin_count }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data afiliasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $affiliations->links() }}
        </div>
    </div>
</div>
@endsection
