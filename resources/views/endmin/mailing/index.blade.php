{{-- resources/views/endmin/mailing/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Mailing')
@section('page_description', 'Kirim template email ke user untuk testing delivery dan tampilan email.')

@section('content')
@php
    $filters = $filters ?? [
        'q' => '',
        'email_status' => '',
    ];
@endphp

<div class="space-y-6">
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Template Aktif</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ count($templates) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Verifikasi email dan reset password.</p>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-4 dark:border-sky-500/20 dark:bg-sky-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600 dark:text-sky-200">User Ditampilkan</p>
            <p class="mt-2 text-3xl font-bold text-sky-800 dark:text-sky-100">{{ $users->count() }}</p>
            <p class="mt-1 text-xs text-sky-700/80 dark:text-sky-100/80">Dari {{ $users->total() }} user sesuai filter.</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-200">Mode Testing</p>
            <p class="mt-2 text-lg font-bold text-amber-800 dark:text-amber-100">Kirim per user</p>
            <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-100/80">Email dikirim memakai notification asli aplikasi.</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Daftar User</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">Pilih template lalu kirim ke email user yang ingin dites.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('endmin.mailing.index') }}" class="mb-5 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama atau email"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status Email</label>
                <select name="email_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="verified" @selected($filters['email_status'] === 'verified')>Terverifikasi</option>
                    <option value="unverified" @selected($filters['email_status'] === 'unverified')>Belum</option>
                </select>
            </div>
            <div class="flex items-end justify-end gap-2">
                <a href="{{ route('endmin.mailing.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Terapkan
                </button>
            </div>
        </form>

        @error('mailing')
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
                {{ $message }}
            </div>
        @enderror

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left dark:border-slate-800">
                        <th class="py-3 pr-4">User</th>
                        <th class="py-3 pr-4">Peran</th>
                        <th class="py-3 pr-4">Status Email</th>
                        <th class="py-3 pr-4">Terdaftar</th>
                        <th class="py-3 pl-4 text-right">Kirim Template</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($users as $user)
                        <tr>
                            <td class="py-3 pr-4">
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $user->name ?: '-' }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 break-all">{{ $user->email }}</p>
                            </td>
                            <td class="py-3 pr-4">
                                @if ($user->isSuperAdmin())
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Super Admin</span>
                                @elseif ($user->isAdminOnly())
                                    <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-100">Admin</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Pengguna</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if ($user->email_verified_at)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Terverifikasi</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Belum</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                {{ optional($user->created_at)->format('Y-m-d') }}
                            </td>
                            <td class="py-3 pl-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                    @foreach ($templates as $templateKey => $templateLabel)
                                        <form action="{{ route('endmin.mailing.send', $user) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="template" value="{{ $templateKey }}">
                                            <button type="submit"
                                                    class="w-full rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:border-indigo-200 hover:bg-indigo-100 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-100 dark:hover:bg-indigo-500/20 sm:w-auto"
                                                    onclick="return confirm('Kirim template {{ $templateLabel }} ke {{ $user->email }}?')">
                                                {{ $templateLabel }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-500 dark:text-slate-400">Tidak ada user sesuai filter.</td>
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
