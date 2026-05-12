{{-- resources/views/layouts/components/admin-sidebar.blade.php --}}
@php
    $navigation = [
        [
            'label' => 'Broadcast',
            'route' => 'admin.broadcasts.index',
            'active' => ['admin.broadcasts.*', 'admin.dashboard'],
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10.5l9-6 9 6M4.5 10.5V18A2.25 2.25 0 006.75 20.25h10.5A2.25 2.25 0 0019.5 18v-7.5M8.25 20.25V15h7.5v5.25" />',
        ],
    ];

    $user = Auth::user();
    $userProfile = $user ? $user->profile : null;
    $userName = $user ? trim((string) ($user->name ?? '')) : '';
    $userEmail = $user ? trim((string) ($user->email ?? '')) : '';
    $userProfileName = $userProfile ? trim((string) ($userProfile->display_name ?? '')) : '';
    $userDisplayName = $userProfileName !== '' ? $userProfileName : ($userName !== '' ? $userName : ($userEmail !== '' ? $userEmail : 'Admin'));
    $userInitial = $user ? mb_strtoupper(mb_substr($userDisplayName, 0, 1)) : 'A';
    $userAvatarUrl = $userProfile?->avatar_url;
    $sidebarTopLabel = $userEmail !== '' ? $userEmail : ($userName !== '' ? $userName : 'Admin');
    $sidebarBottomLabel = $userDisplayName;
@endphp

<aside id="app-sidebar"
    class="bg-white/95 text-slate-600 w-64 h-screen fixed inset-y-0 left-0 z-30 px-5 py-6 flex flex-col border-r border-slate-100/70 shadow-[0_10px_40px_rgba(15,23,42,0.08)] backdrop-blur-xl dark:bg-slate-950/80 dark:text-slate-200 dark:border-slate-900/80">
    <div class="flex items-center gap-3 mb-8 h-12 sidebar-brand-wrapper transition-all duration-300">
        <div class="sidebar-brand-icon hidden h-10 w-10 rounded-2xl bg-indigo-500/90 text-white font-semibold grid place-items-center">PL</div>
        <div class="flex flex-col">
            <span class="text-2xl font-extrabold sidebar-brand-text text-slate-900 dark:text-white">PolyLife</span>
            <span class="text-xs tracking-[0.35em] uppercase text-slate-400 dark:text-slate-500 sidebar-brand-text">Admin</span>
        </div>
    </div>

    <nav class="space-y-1 flex-1 pb-6">
        @foreach ($navigation as $item)
            @php
                $patterns = collect($item['active'] ?? [])->filter()->whenEmpty(fn ($c) => collect([$item['route']]));
                $isActive = $patterns->contains(fn ($pattern) => request()->routeIs($pattern));
            @endphp
            <a href="{{ route($item['route']) }}"
               class="sidebar-link group flex items-center gap-3 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-200 dark:hover:text-white"
               data-active="{{ $isActive ? 'true' : 'false' }}" @if($isActive) aria-current="page" @endif>
                <span class="sidebar-indicator" aria-hidden="true"></span>
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition group-hover:bg-white/80 group-hover:text-indigo-600 dark:bg-slate-900/40 dark:text-slate-300 dark:group-hover:bg-slate-900/60">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        {!! $item['icon'] !!}
                    </svg>
                </span>
                <span class="sidebar-link-text">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="sidebar-footer mt-auto border-t border-slate-100/60 dark:border-slate-900/60 pt-5 flex flex-col gap-4">
        <div class="flex items-center gap-3 min-w-0 sidebar-user">
            @auth
                <a href="{{ route('admin.profile') }}"
                   class="sidebar-user-avatar grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-2xl bg-indigo-500 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-white dark:bg-indigo-400/30 dark:text-indigo-100 dark:focus:ring-indigo-300 dark:focus:ring-offset-slate-950 {{ request()->routeIs('admin.profile') ? 'ring-2 ring-indigo-300 dark:ring-indigo-400/70' : '' }}"
                   title="Buka profil admin"
                   aria-label="Buka profil admin"
                   data-profile-avatar-frame
                   data-profile-avatar-initial="{{ $userInitial }}"
                   data-profile-avatar-alt=""
                   data-sidebar-profile-link>
                    @if ($userAvatarUrl)
                        <img src="{{ $userAvatarUrl }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ $userInitial }}
                    @endif
                </a>
            @else
                <div class="sidebar-user-avatar h-10 w-10 rounded-2xl bg-indigo-500 text-white font-semibold grid place-items-center dark:bg-indigo-400/30 dark:text-indigo-100">
                    {{ $userInitial }}
                </div>
            @endauth
            <div class="min-w-0 flex-1 sidebar-user-text">
                <p class="text-sm leading-tight font-medium text-slate-700 dark:text-slate-100 break-all">
                    @auth {{ $sidebarTopLabel }} @else Admin @endauth
                </p>
            </div>
        </div>

        @auth
            <div class="w-full space-y-1">
                <p class="text-[11px] uppercase tracking-wide text-slate-400 dark:text-slate-500">Masuk sebagai</p>
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 break-all" data-profile-display-name>
                    {{ $sidebarBottomLabel }}
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2" data-theme-logout-form>
                    @csrf
                    <input type="hidden" name="theme_preference" value="" disabled data-theme-logout-input>
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center rounded-xl border border-slate-200/80 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:text-white">
                        Logout
                    </button>
                </form>
            </div>
        @endauth

        <div class="sidebar-footer-controls flex items-center flex-shrink-0 gap-2">
            <button type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200/70 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:text-slate-300"
                data-theme-toggle aria-pressed="false" title="Ubah mode tampilan">
                <span class="sr-only">Toggle dark mode</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15.25A6.75 6.75 0 0112.75 7 6.75 6.75 0 1021 15.25z" />
                </svg>
            </button>
            <button type="button"
                class="relative inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200/70 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:text-slate-300"
                data-sidebar-toggle aria-pressed="false" title="Sembunyikan sidebar">
                <span class="sr-only">Toggle sidebar</span>
                <svg xmlns="http://www.w3.org/2000/svg" data-sidebar-toggle-arrow
                    class="h-5 w-5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 6l-6 6 6 6" />
                </svg>
            </button>
        </div>
    </div>
</aside>
