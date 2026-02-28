{{-- resources/views/endmin/dashboard/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Dashboard Endmin')
@section('page_description', 'Pusat kontrol super admin untuk monitoring user, verifikasi, dan audit.')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Total User</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $stats['total_users'] }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Semua akun terdaftar</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-200">Pending Verifikasi</p>
            <p class="mt-2 text-3xl font-bold text-amber-800 dark:text-amber-100">{{ $stats['pending_affiliations'] }}</p>
            <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-100/80">Afiliasi belum diverifikasi</p>
        </div>
        <div class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4 dark:border-rose-500/20 dark:bg-rose-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-200">Akun Banned</p>
            <p class="mt-2 text-3xl font-bold text-rose-800 dark:text-rose-100">{{ $stats['banned_users'] }}</p>
            <p class="mt-1 text-xs text-rose-700/80 dark:text-rose-100/80">Perlu review jika melonjak</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Belum Verifikasi Email</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $stats['unverified_emails'] }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Akun dengan email belum valid</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Antrian Prioritas Verifikasi</h3>
                <a href="{{ route('endmin.verifications.index') }}"
                   class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-300">
                    Buka Verifikasi
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left dark:border-slate-800">
                            <th class="py-2 pr-3">User</th>
                            <th class="py-2 pr-3">Email</th>
                            <th class="py-2 pr-3">Afiliasi</th>
                            <th class="py-2 pr-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse ($pendingQueue as $queueUser)
                            <tr>
                                <td class="py-2 pr-3 text-slate-800 dark:text-slate-100">{{ $queueUser->name ?: '-' }}</td>
                                <td class="py-2 pr-3 text-slate-500 dark:text-slate-300">{{ $queueUser->email }}</td>
                                <td class="py-2 pr-3 text-slate-500 dark:text-slate-300">{{ $queueUser->affiliation_name ?: '-' }}</td>
                                <td class="py-2 pr-3">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($queueUser->email_verified_at)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Email OK</span>
                                        @else
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Email Pending</span>
                                        @endif
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                            {{ ucfirst((string) ($queueUser->affiliation_status ?? 'pending')) }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Tidak ada antrian verifikasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Distribusi Role</h3>
            <div class="space-y-3">
                <div class="rounded-xl bg-indigo-50 p-3 dark:bg-indigo-500/10">
                    <p class="text-xs text-indigo-600 dark:text-indigo-200">Super Admin</p>
                    <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-100">{{ $roleDistribution['super_admin'] }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 p-3 dark:bg-sky-500/10">
                    <p class="text-xs text-sky-600 dark:text-sky-200">Admin</p>
                    <p class="text-2xl font-bold text-sky-700 dark:text-sky-100">{{ $roleDistribution['admin'] }}</p>
                </div>
                <div class="rounded-xl bg-slate-100 p-3 dark:bg-slate-800">
                    <p class="text-xs text-slate-600 dark:text-slate-300">Pengguna</p>
                    <p class="text-2xl font-bold text-slate-700 dark:text-slate-100">{{ $roleDistribution['user'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Aktivitas Terbaru Endmin</h3>
            <a href="{{ route('endmin.audit-logs.index') }}"
               class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-300">
                Lihat Semua Log
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left dark:border-slate-800">
                        <th class="py-2 pr-3">Waktu</th>
                        <th class="py-2 pr-3">Actor</th>
                        <th class="py-2 pr-3">Modul</th>
                        <th class="py-2 pr-3">Aksi</th>
                        <th class="py-2 pr-3">Target</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($recentLogs as $log)
                        <tr>
                            <td class="py-2 pr-3 text-slate-500 dark:text-slate-300">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="py-2 pr-3 text-slate-800 dark:text-slate-100">{{ $log->actor?->name ?: ($log->actor?->email ?: '-') }}</td>
                            <td class="py-2 pr-3 text-slate-500 dark:text-slate-300">{{ $log->module }}</td>
                            <td class="py-2 pr-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ $log->action }}</span></td>
                            <td class="py-2 pr-3 text-slate-500 dark:text-slate-300">{{ $log->targetUser?->name ?: ($log->targetUser?->email ?: '-') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada audit log.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
