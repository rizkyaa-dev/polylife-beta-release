{{-- resources/views/admin/profile.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Profil Admin')
@section('page_description', 'Kelola tampilan akun admin dan lihat kewenangan broadcast afiliasi.')

@section('content')
@php
    $profile = $user?->profile;
    $displayName = trim((string) ($profile?->display_name ?: $user?->name));
    $displayName = $displayName !== '' ? $displayName : 'Admin';
    $avatarUrl = $profile?->avatar_url;
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $themeLabel = match ($profile?->theme_preference) {
        'dark' => 'Gelap',
        'light' => 'Terang',
        default => 'Ikuti perangkat',
    };
    $accountStatus = ($user->account_status ?? 'active') === 'banned' ? 'Suspend' : 'Aktif';
    $hasVerifiedAffiliation = $user->affiliation_status === 'verified'
        && (filled($user->affiliation_template_id) || filled($user->affiliation_name));
    $affiliationStatusLabel = match (true) {
        $hasVerifiedAffiliation => 'Terverifikasi',
        $user->affiliation_status === 'rejected' => 'Ditolak',
        default => 'Menunggu review',
    };
    $affiliationStatusClass = match (true) {
        $hasVerifiedAffiliation => 'text-emerald-600 dark:text-emerald-300',
        $user->affiliation_status === 'rejected' => 'text-rose-600 dark:text-rose-300',
        default => 'text-amber-600 dark:text-amber-300',
    };
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(28rem,0.95fr)] xl:items-center">
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center">
                <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl border border-indigo-100 bg-indigo-50 text-xl font-bold text-indigo-700 shadow-sm sm:h-20 sm:w-20 sm:rounded-3xl sm:text-2xl dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-100"
                     data-profile-avatar-frame
                     data-profile-avatar-initial="{{ $initial }}"
                     data-profile-avatar-alt="Foto profil {{ $displayName }}">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="Foto profil {{ $displayName }}" class="h-full w-full object-cover">
                    @else
                        <div class="grid h-full w-full place-items-center">{{ $initial }}</div>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300 sm:text-sm">Panel admin</p>
                    <h2 class="mt-1 break-words text-xl font-bold text-gray-900 sm:text-2xl dark:text-slate-100" data-profile-display-name>{{ $displayName }}</h2>
                    <p class="mt-1 break-all text-sm text-gray-500 dark:text-slate-400">{{ $user->email }}</p>
                </div>
            </div>

            <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="min-w-0 rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Role</p>
                    <p class="mt-1 truncate text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $user->roleLabel() }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Akun</p>
                    <p class="mt-1 truncate text-sm font-semibold {{ $accountStatus === 'Aktif' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">{{ $accountStatus }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Tema</p>
                    <p class="mt-1 truncate text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $themeLabel }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Afiliasi</p>
                    <p class="mt-1 truncate text-sm font-semibold {{ $affiliationStatusClass }}">{{ $affiliationStatusLabel }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Kewenangan broadcast</p>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Target afiliasi admin</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400">Admin hanya dapat membuat broadcast untuk target afiliasi yang tersedia di bawah ini.</p>
        </div>

        <div class="mt-4">
            @if (count($targetOptions) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach ($targetOptions as $option)
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">
                            {{ $option['label'] }}
                        </span>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    Akun admin ini belum memiliki afiliasi terverifikasi. Ajukan afiliasi agar super admin dapat meninjau dan mengaktifkan target broadcast.
                </div>
            @endif
        </div>

        @if (count($targetOptions) === 0)
            <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                <livewire:profile.update-affiliation-request-form />
            </div>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <livewire:profile.update-profile-details-form context="admin" />
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <livewire:profile.update-password-form />
            </div>
        </div>
    </div>
</div>
@endsection
