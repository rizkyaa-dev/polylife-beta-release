{{-- resources/views/endmin/admins/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Manajemen Admin')
@section('page_description', 'Promosi pengguna menjadi admin, suspend admin, dan cabut otoritas admin.')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-4 dark:border-sky-500/20 dark:bg-sky-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600 dark:text-sky-200">Admin</p>
            <p class="mt-2 text-3xl font-bold text-sky-800 dark:text-sky-100">{{ $summary['admins'] }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Admin Aktif</p>
            <p class="mt-2 text-3xl font-bold text-emerald-800 dark:text-emerald-100">{{ $summary['active_admins'] }}</p>
        </div>
        <div class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4 dark:border-rose-500/20 dark:bg-rose-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-200">Admin Suspend</p>
            <p class="mt-2 text-3xl font-bold text-rose-800 dark:text-rose-100">{{ $summary['suspended_admins'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Kandidat User</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $summary['candidate_users'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('endmin.admins.index') }}" class="grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama, email, afiliasi"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Role</label>
                <select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                    <option value="user" @selected($filters['role'] === 'user')>Pengguna</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="banned" @selected($filters['status'] === 'banned')>Banned</option>
                </select>
            </div>
            <div class="md:col-span-4 flex items-center justify-end gap-2">
                <a href="{{ route('endmin.admins.index') }}"
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
                        <th class="py-3 pr-4">Nama</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Afiliasi</th>
                        <th class="py-3 pr-4">Role</th>
                        <th class="py-3 pr-4">Status Akun</th>
                        <th class="py-3 pl-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($users as $user)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900 dark:text-slate-100">{{ $user->name ?: '-' }}</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $user->email }}</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $user->affiliation_name ?: '-' }}</td>
                            <td class="py-3 pr-4">
                                @if ($user->isAdminOnly())
                                    <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-100">Admin</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Pengguna</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if (($user->account_status ?? 'active') === 'banned')
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-100">Banned</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Active</span>
                                @endif
                            </td>
                            <td class="py-3 pl-4">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if ($user->isAdminOnly())
                                        @if (($user->account_status ?? 'active') === 'banned')
                                            <form method="POST" action="{{ route('endmin.admins.activate', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-100 dark:hover:bg-emerald-500/20">
                                                    Aktifkan
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('endmin.admins.suspend', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="reason" value="Admin disuspend dari menu manajemen admin.">
                                                <button type="submit" class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-100 dark:hover:bg-amber-500/20">
                                                    Suspend
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('endmin.admins.demote', $user) }}" onsubmit="return confirm('Cabut status admin untuk akun ini?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-lg bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-100 dark:hover:bg-rose-500/20">
                                                Cabut Admin
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('endmin.admins.promote', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-lg bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-100 dark:hover:bg-indigo-500/20">
                                                Jadikan Admin
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data akun.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
