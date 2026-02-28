{{-- resources/views/endmin/audit-logs/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Audit Log Endmin')
@section('page_description', 'Riwayat aksi sensitif yang dilakukan dari panel super admin.')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('endmin.audit-logs.index') }}" class="grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Module, action, actor, target"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Module</label>
                <select name="module" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    @foreach ($availableModules as $module)
                        <option value="{{ $module }}" @selected($filters['module'] === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Action</label>
                <select name="action" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    @foreach ($availableActions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-4 flex items-center justify-end gap-2">
                <a href="{{ route('endmin.audit-logs.index') }}"
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
                        <th class="py-3 pr-4">Waktu</th>
                        <th class="py-3 pr-4">Actor</th>
                        <th class="py-3 pr-4">Module</th>
                        <th class="py-3 pr-4">Action</th>
                        <th class="py-3 pr-4">Target</th>
                        <th class="py-3 pr-4">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="py-3 pr-4 text-slate-500 dark:text-slate-300">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3 pr-4 text-slate-800 dark:text-slate-100">
                                {{ $log->actor?->name ?: ($log->actor?->email ?: '-') }}
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $log->module }}</td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $log->action }}</span>
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                {{ $log->targetUser?->name ?: ($log->targetUser?->email ?: '-') }}
                            </td>
                            <td class="py-3 pr-4 text-slate-500 dark:text-slate-400">{{ $log->ip_address ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada log.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
