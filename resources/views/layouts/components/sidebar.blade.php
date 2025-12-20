{{-- resources/views/layouts/components/sidebar.blade.php --}}
@php
    $guestMode = $guestMode ?? request()->routeIs('guest.*');
    $navigation = [
        [
            'label' => 'Beranda',
            'route' => 'dashboard',
            'route_guest' => 'guest.home',
            'active' => ['dashboard', 'workspace.home'],
            'guest_active' => ['guest.home'],
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12l8.954-8.955a1.125 1.125 0 011.592 0L21.75 12M4.5 9.75v9a1.5 1.5 0 001.5 1.5h12a1.5 1.5 0 001.5-1.5v-9" />',
        ],
        [
            'label' => 'Keuangan',
            'route' => 'keuangan.index',
            'route_guest' => 'guest.keuangan.index',
            'active' => ['keuangan.*'],
            'guest_active' => ['guest.keuangan.*'],
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10.5h18M3 7.5h18m-9 9.75h9.75A1.5 1.5 0 0023.25 15.75V6.75A1.5 1.5 0 0021.75 5.25H2.25A1.5 1.5 0 00.75 6.75v9A1.5 1.5 0 002.25 17.25H12z" />',
        ],
        [
            'label' => 'Jadwal',
            'route' => 'jadwal.index',
            'route_guest' => 'guest.jadwal.index',
            'active' => ['jadwal.*'],
            'guest_active' => ['guest.jadwal.*'],
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3M3 10.5h18M6 21h12a3 3 0 003-3V7.5a3 3 0 00-3-3H6a3 3 0 00-3 3V18a3 3 0 003 3z" />',
        ],
        [
            'label' => 'To-Do',
            'route' => 'todolist.index',
            'route_guest' => 'guest.todolist.index',
            'active' => ['todolist.*'],
            'guest_active' => ['guest.todolist.*'],
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 6.75h9m-9 4.5h9m-9 4.5h9M16.5 12l1.5 1.5 3-3" />',
        ],
        [
            'label' => 'Catatan',
            'route' => 'catatan.index',
            'route_guest' => 'guest.catatan.index',
            'active' => ['catatan.*'],
            'guest_active' => ['guest.catatan.*'],
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.5 3.75h9.75a1.5 1.5 0 011.5 1.5V18L12 15.75 8.25 18V5.25a1.5 1.5 0 00-1.5-1.5z" />',
        ],
        [
            'label' => 'IPK',
            'route' => 'ipk.index',
            'route_guest' => 'guest.ipk.index',
            'active' => ['ipk.*'],
            'guest_active' => ['guest.ipk.*'],
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 20.25h18M4.5 15.75l3-3 3 3 3-3 3 3 3-3" />',
        ],
    ];

    $user = Auth::user();
    $userName = $user ? trim((string) ($user->name ?? '')) : '';
    $userEmail = $user ? trim((string) ($user->email ?? '')) : '';
    $userDisplayName = $userName !== '' ? $userName : ($userEmail !== '' ? $userEmail : 'Pengguna');
    $userInitial = $user ? mb_strtoupper(mb_substr($userDisplayName, 0, 1)) : 'P';
    $sidebarTopLabel = $userEmail !== '' ? $userEmail : ($userName !== '' ? $userName : 'Pengguna');
    $sidebarBottomLabel = $userName !== '' ? $userName : ($userEmail !== '' ? $userEmail : 'Pengguna');
@endphp

<aside id="app-sidebar"
    class="bg-white/95 text-slate-600 w-64 h-screen fixed inset-y-0 left-0 z-30 px-5 py-6 flex flex-col border-r border-slate-100/70 shadow-[0_10px_40px_rgba(15,23,42,0.08)] backdrop-blur-xl dark:bg-slate-950/80 dark:text-slate-200 dark:border-slate-900/80">
    <div class="flex items-center gap-3 mb-8 h-12 sidebar-brand-wrapper transition-all duration-300">
        <div class="sidebar-brand-icon hidden h-10 w-10 rounded-2xl bg-indigo-500/90 text-white font-semibold grid place-items-center">PL</div>
        <div class="flex flex-col">
            <span class="text-2xl font-extrabold sidebar-brand-text text-slate-900 dark:text-white">PolyLife</span>
            <span class="text-xs tracking-[0.35em] uppercase text-slate-400 dark:text-slate-500 sidebar-brand-text">
                {{ $guestMode ? 'Guest' : 'Workspace' }}
            </span>
        </div>
    </div>

    <nav class="space-y-1 flex-1 pb-6">
        @foreach ($navigation as $item)
            @php
                $routeName = $guestMode ? ($item['route_guest'] ?? $item['route']) : $item['route'];
                $activePatterns = $guestMode ? ($item['guest_active'] ?? []) : ($item['active'] ?? []);
                $patterns = collect($activePatterns)->filter()->whenEmpty(fn ($c) => collect([$routeName]));
                $isActive = $patterns->contains(fn ($pattern) => request()->routeIs($pattern));
            @endphp
            <a href="{{ route($routeName) }}"
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
            <div class="sidebar-user-avatar h-10 w-10 rounded-2xl bg-indigo-500 text-white font-semibold grid place-items-center dark:bg-indigo-400/30 dark:text-indigo-100">
                {{ $userInitial }}
            </div>
            <div class="min-w-0 flex-1 sidebar-user-text">
                <p class="text-sm leading-tight font-medium text-slate-700 dark:text-slate-100 break-all">
                    @auth {{ $sidebarTopLabel }} @else Mode tamu @endauth
                </p>
            </div>
        </div>

        @auth
            <div class="w-full space-y-1">
                <p class="text-[11px] uppercase tracking-wide text-slate-400 dark:text-slate-500">Masuk sebagai</p>
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 break-all">
                    {{ $sidebarBottomLabel }}
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
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
