@extends('layouts.app')

@section('page_title', 'Profil')
@section('page_description', 'Kelola informasi akun, foto profil, dan preferensi workspace.')

@section('content')
@php
    $user = auth()->user();
    $profile = $user?->profile;
    $displayName = trim((string) ($profile?->display_name ?: $user?->name));
    $displayName = $displayName !== '' ? $displayName : 'Pengguna';
    $avatarUrl = $profile?->avatar_url;
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $themeLabel = match ($profile?->theme_preference) {
        'dark' => 'Gelap',
        'light' => 'Terang',
        default => 'Ikuti perangkat',
    };
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-3xl border border-indigo-100 bg-indigo-50 text-2xl font-bold text-indigo-700 shadow-sm dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-100"
                     data-profile-avatar-frame
                     data-profile-avatar-initial="{{ $initial }}"
                     data-profile-avatar-alt="Foto profil {{ $displayName }}">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="Foto profil {{ $displayName }}" class="h-full w-full object-cover">
                    @else
                        <div class="grid h-full w-full place-items-center">{{ $initial }}</div>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-300">Profil workspace</p>
                    <h2 class="mt-1 break-words text-2xl font-bold text-gray-900 dark:text-slate-100" data-profile-display-name>{{ $displayName }}</h2>
                    <p class="mt-1 break-all text-sm text-gray-500 dark:text-slate-400">{{ $user->email }}</p>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-3 md:min-w-[25rem]">
                <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Role</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $user->roleLabel() }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Tema</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $themeLabel }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Status</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-600 dark:text-emerald-300">
                        {{ $user->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum verifikasi' }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <livewire:profile.update-profile-details-form />
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <livewire:profile.update-password-form />
            </div>

            <div class="rounded-2xl border border-rose-100 bg-white p-6 shadow-sm dark:border-rose-500/30 dark:bg-slate-900">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</div>
@endsection
