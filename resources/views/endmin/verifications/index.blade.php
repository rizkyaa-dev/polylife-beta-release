{{-- resources/views/endmin/verifications/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Verifikasi User')
@section('page_description', 'Kelola status verifikasi email dan afiliasi pengguna.')

@section('content')
@php
    $filters = $filters ?? [
        'role' => '',
        'affiliation_status' => '',
        'email_status' => '',
        'q' => '',
    ];
@endphp
<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Daftar Verifikasi User</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">Edit verifikasi email, data afiliasi, dan identitas per akun.</p>
            </div>
            <a href="{{ route('endmin.users.index') }}"
               class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                Kembali ke Akun
            </a>
        </div>

        <form method="GET" action="{{ route('endmin.verifications.index') }}" class="mb-5 grid gap-3 md:grid-cols-5">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama, email, afiliasi, student ID"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Role</label>
                <select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="super_admin" @selected($filters['role'] === 'super_admin')>Super Admin</option>
                    <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                    <option value="user" @selected($filters['role'] === 'user')>Pengguna</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Afiliasi</label>
                <select name="affiliation_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="pending" @selected($filters['affiliation_status'] === 'pending')>Pending</option>
                    <option value="verified" @selected($filters['affiliation_status'] === 'verified')>Verified</option>
                    <option value="rejected" @selected($filters['affiliation_status'] === 'rejected')>Rejected</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</label>
                <select name="email_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="verified" @selected($filters['email_status'] === 'verified')>Verified</option>
                    <option value="unverified" @selected($filters['email_status'] === 'unverified')>Belum</option>
                </select>
            </div>
            <div class="md:col-span-5 flex items-center justify-end gap-2">
                <a href="{{ route('endmin.verifications.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Terapkan Filter
                </button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-100 dark:border-slate-800">
                        <th class="py-3 pr-4">Nama</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Verifikasi Email</th>
                        <th class="py-3 pr-4">Afiliasi & Student ID</th>
                        <th class="py-3 pl-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($users as $user)
                        @php
                            $affiliationStatus = strtolower(trim((string) ($user->affiliation_status ?? 'pending')));
                            $affiliationName = trim((string) ($user->affiliation_name ?? ''));
                            $studentIdTypeRaw = trim((string) ($user->student_id_type ?? ''));
                            $studentIdType = $studentIdTypeRaw !== '' ? strtoupper($studentIdTypeRaw) : 'ID';
                            $studentIdNumber = trim((string) ($user->student_id_number ?? ''));
                            $normalizedRole = strtolower(trim((string) ($user->role ?? '')));
                            $adminLevel = (int) ($user->is_admin ?? 0);
                            $isSuperAdmin = $user->isSuperAdmin() || $adminLevel === 1 || $normalizedRole === 'super_admin';
                            $isAdminOnly = $user->isAdminOnly() || $adminLevel === 2 || $normalizedRole === 'admin';
                            $affiliationPillClass = $affiliationStatus === 'verified'
                                ? 'inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100'
                                : ($affiliationStatus === 'rejected'
                                    ? 'inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-100'
                                    : 'inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200');
                        @endphp
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900 dark:text-slate-100">
                                {{ $user->name ?: '-' }}
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300 break-all">
                                {{ $user->email }}
                            </td>
                            <td class="py-3 pr-4">
                                @if ($user->email_verified_at)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">
                                        Terverifikasi
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">
                                        Belum
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                <div class="space-y-1">
                                    @if ($affiliationName !== '')
                                        <div class="inline-flex items-center gap-2">
                                            @if ($isSuperAdmin)
                                                <span class="text-sm leading-none" style="color:#6366f1;" aria-hidden="true">●</span>
                                            @elseif ($isAdminOnly)
                                                <span class="text-sm leading-none text-black dark:text-white" aria-hidden="true">●</span>
                                            @endif
                                            <span class="{{ $affiliationPillClass }}">
                                                {{ $affiliationName }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="font-medium text-slate-900 dark:text-slate-100">-</p>
                                    @endif
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $studentIdType }}: {{ $studentIdNumber !== '' ? $studentIdNumber : '-' }}
                                    </p>
                                </div>
                            </td>
                            <td class="py-3 pl-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                    <form action="{{ route('endmin.verifications.update', $user) }}" method="POST" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        @csrf
                                        @method('PATCH')
                                        <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                                            <input type="checkbox"
                                                   name="email_verified"
                                                   value="1"
                                                   class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                   @checked((bool) $user->email_verified_at)>
                                            Email verified
                                        </label>
                                        <select name="affiliation_status"
                                                class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                            <option value="pending" @selected($affiliationStatus === 'pending')>Pending</option>
                                            <option value="verified" @selected($affiliationStatus === 'verified')>Verified</option>
                                            <option value="rejected" @selected($affiliationStatus === 'rejected')>Rejected</option>
                                        </select>
                                        <button type="submit"
                                                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                                            Simpan
                                        </button>
                                    </form>
                                    <a href="{{ route('endmin.verifications.edit', $user) }}"
                                       class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:text-slate-200">
                                        Edit Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-500 dark:text-slate-400">Belum ada akun.</td>
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
