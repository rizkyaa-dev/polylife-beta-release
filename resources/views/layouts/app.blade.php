<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
    <title>PolyLife</title>
    <script>
        (function () {
            const storageKey = 'theme';
            const root = document.documentElement;
            try {
                const stored = localStorage.getItem(storageKey);
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    root.classList.add('dark');
                } else {
                    root.classList.remove('dark');
                }
                root.dataset.theme = root.classList.contains('dark') ? 'dark' : 'light';
            } catch (err) {
                console.warn('Theme init issue', err);
            }
        })();
    </script>
    <script>
        (() => {
            const storageKey = 'sidebar:collapsed';
            try {
                const isDesktop = window.matchMedia('(min-width: 1025px)').matches;
                const collapsed = isDesktop && localStorage.getItem(storageKey) === 'true';
                if (collapsed) {
                    const root = document.documentElement;
                    root.classList.add('sidebar-collapsed');
                    if (document.body) {
                        document.body.classList.add('sidebar-collapsed');
                    }
                }
            } catch (err) {
                console.warn('Sidebar init issue', err);
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --sidebar-width: 16.5rem;
            --sidebar-peek: 4.5rem;
            --shell-ease: cubic-bezier(0.22, 1, 0.36, 1);
            --sidebar-mobile-width: min(92vw, 19rem);
        }

        #app-shell {
            padding-left: var(--sidebar-width);
            transition: padding-left 320ms var(--shell-ease);
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(4px);
            z-index: 20;
            opacity: 0;
            pointer-events: none;
            transition: opacity 200ms ease;
        }

        .sidebar-open .sidebar-backdrop {
            opacity: 1;
            pointer-events: auto;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        #app-sidebar {
            width: var(--sidebar-width);
            transform: translateX(0);
            overflow: hidden;
            transition: width 320ms var(--shell-ease), padding 320ms var(--shell-ease), box-shadow 200ms ease, backdrop-filter 250ms ease, transform 280ms ease;
            will-change: transform;
            height: 100vh;
            max-height: 100vh;
            z-index: 60;
        }

        @supports (height: 100dvh) {
            #app-sidebar {
                height: 100dvh;
                max-height: 100dvh;
            }
        }

        .sidebar-link {
            gap: 0.75rem;
            border-radius: 1.15rem;
            padding: 0.65rem 0.9rem;
            position: relative;
            transition: gap 200ms ease, padding 200ms ease, color 200ms ease;
            isolation: isolate;
        }

        .sidebar-link::after {
            content: '';
            position: absolute;
            inset: 0.2rem;
            border-radius: 0.95rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(56, 189, 248, 0.2));
            opacity: 0;
            z-index: -1;
            transition: opacity 200ms ease;
        }

        .sidebar-link[data-active="true"]::after {
            opacity: 1;
        }

        .sidebar-indicator {
            position: absolute;
            left: -0.75rem;
            top: 0.45rem;
            bottom: 0.45rem;
            width: 0.3rem;
            border-radius: 9999px;
            background: linear-gradient(180deg, #c7d2fe, #6366f1);
            transform: scaleY(0.5);
            opacity: 0;
            transition: transform 200ms ease, opacity 200ms ease;
        }

        .sidebar-link[data-active="true"] .sidebar-indicator {
            opacity: 1;
            transform: scaleY(1);
        }

        .sidebar-link-text,
        .sidebar-brand-text {
            display: inline-flex;
            white-space: nowrap;
            transition: opacity 200ms ease, transform 200ms ease, width 200ms ease;
        }

        .sidebar-brand-icon {
            display: none;
            white-space: nowrap;
            transition: opacity 200ms ease, transform 200ms ease;
        }

        .sidebar-user-text {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            transition: opacity 200ms ease, transform 200ms ease;
        }

        body:not(.sidebar-collapsed) #app-sidebar .sidebar-link-text,
        body:not(.sidebar-collapsed) #app-sidebar .sidebar-user-text,
        body:not(.sidebar-collapsed) #app-sidebar .sidebar-brand-text {
            opacity: 1;
            transform: none;
            width: auto;
            overflow: visible;
        }

        .sidebar-user {
            transition: justify-content 200ms ease;
        }

        .sidebar-user-avatar {
            transition: margin 200ms ease;
        }

        body:not(.sidebar-collapsed) #app-sidebar .sidebar-user {
            display: flex;
        }

        .sidebar-footer {
            gap: 0.75rem;
        }

        .sidebar-footer-controls {
            margin-left: auto;
            gap: 0.75rem;
            justify-content: flex-end;
            transition: gap 200ms ease, justify-content 200ms ease, margin 200ms ease, width 200ms ease;
        }

        @media (min-width: 1025px) {
            .sidebar-collapsed #app-shell {
                padding-left: var(--sidebar-peek);
            }

            .sidebar-collapsed #app-sidebar {
                width: var(--sidebar-peek);
                padding-left: 0.75rem;
                padding-right: 0.75rem;
                box-shadow: none;
            }

            .sidebar-collapsed #app-sidebar .sidebar-link {
                justify-content: center;
                gap: 0;
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }

            .sidebar-collapsed #app-sidebar .sidebar-link::after {
                inset: 0.35rem;
            }

            .sidebar-collapsed #app-sidebar .sidebar-indicator {
                opacity: 0;
            }

            .sidebar-collapsed #app-sidebar .sidebar-link-text,
            .sidebar-collapsed #app-sidebar .sidebar-user-text,
            .sidebar-collapsed #app-sidebar .sidebar-brand-text {
                opacity: 0;
                transform: translateX(-8px);
                width: 0;
                overflow: hidden;
                display: none;
            }

            .sidebar-collapsed #app-sidebar .sidebar-brand-icon {
                display: inline-block;
                opacity: 1;
                transform: none;
                width: auto;
            }

            .sidebar-collapsed #app-sidebar .sidebar-brand-wrapper {
                justify-content: center;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
            }

            .sidebar-collapsed #app-sidebar .sidebar-brand-icon {
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                background: transparent;
                color: transparent;
                box-shadow: none;
                border-color: transparent;
            }

            .sidebar-collapsed #app-sidebar .sidebar-user {
                display: flex;
                justify-content: center;
            }

            .sidebar-collapsed #app-sidebar .sidebar-user-avatar {
                margin: 0;
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer {
                justify-content: center;
                gap: 0.5rem;
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer-controls {
                gap: 0.35rem;
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }

            .sidebar-collapsed #app-sidebar [data-theme-toggle],
            .sidebar-collapsed #app-sidebar .sidebar-user {
                display: none;
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer {
                padding: 0.4rem 0;
                width: 100%;
                justify-content: center;
            }

            .sidebar-collapsed #app-sidebar .sidebar-user-avatar {
                background: linear-gradient(145deg, #6366f1, #8b5cf6);
                color: #fff;
                box-shadow: 0 10px 24px rgba(99, 102, 241, 0.25);
                border: 1px solid rgba(99, 102, 241, 0.22);
            }

            .dark .sidebar-collapsed #app-sidebar .sidebar-user-avatar {
                background: linear-gradient(145deg, #4338ca, #312e81);
                box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
                border-color: rgba(148, 163, 184, 0.22);
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer-controls {
                gap: 0.6rem;
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer-controls button {
                height: 2.75rem;
                width: 2.75rem;
                border-color: transparent;
                background: rgba(255, 255, 255, 0.85);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
                backdrop-filter: blur(12px);
                transition: transform 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer-controls button:hover {
                transform: translateY(-1px);
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
                background: rgba(255, 255, 255, 0.95);
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer-controls [data-sidebar-toggle] {
                transform: translateY(3px);
            }

            .sidebar-collapsed #app-sidebar .sidebar-footer-controls [data-sidebar-toggle]:hover {
                transform: translateY(2px);
            }

            .dark .sidebar-collapsed #app-sidebar .sidebar-footer-controls button {
                background: rgba(15, 23, 42, 0.72);
                border-color: rgba(255, 255, 255, 0.05);
                box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35);
            }

            .dark .sidebar-collapsed #app-sidebar .sidebar-footer-controls button:hover {
                background: rgba(15, 23, 42, 0.85);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
            }

            [data-sidebar-toggle-arrow] {
                transition: transform 200ms ease, opacity 200ms ease;
            }

            .sidebar-collapsed [data-sidebar-toggle-arrow] {
                transform: rotate(180deg) translateY(1px);
            }

            .sidebar-collapsed [data-sidebar-toggle]:hover [data-sidebar-toggle-arrow] {
                transform: rotate(180deg) translateY(1px);
            }
        }

        .app-header {
            position: sticky;
            top: 0;
            z-index: 40;
            transition: transform 220ms var(--shell-ease), box-shadow 200ms ease, border-color 200ms ease, background-color 200ms ease;
        }

        .app-header.is-hidden {
            transform: translateY(-100%);
        }

        .app-header.is-raised {
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border-color: rgba(148, 163, 184, 0.4);
            background-color: rgba(255, 255, 255, 0.92);
        }

        .dark .app-header.is-raised {
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.55);
            border-color: rgba(30, 41, 59, 0.8);
            background-color: rgba(15, 23, 42, 0.9);
        }

        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 0;
                --sidebar-peek: 0;
            }

            #app-shell {
                padding-left: 0;
            }

            #app-sidebar {
                width: var(--sidebar-mobile-width);
                max-width: 100vw;
                transform: translateX(-110%);
                box-shadow: 0 16px 40px rgba(15, 23, 42, 0.24);
                z-index: 60;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                top: 0;
                padding-top: calc(1.1rem + env(safe-area-inset-top));
                padding-bottom: calc(1.1rem + env(safe-area-inset-bottom));
                border-top-right-radius: 1.25rem;
                border-bottom-right-radius: 1.25rem;
            }

            .sidebar-open #app-sidebar {
                transform: translateX(0);
            }

            .app-header {
                position: sticky;
                top: 0;
            }

            #app-sidebar [data-sidebar-toggle] {
                display: none;
            }

            .mobile-nav-trigger {
                display: inline-flex;
            }

            #app-sidebar nav {
                flex: 1 1 auto;
                overflow-y: auto;
                padding-bottom: 1.1rem;
                overscroll-behavior: contain;
            }

            #app-sidebar .sidebar-footer {
                flex-shrink: 0;
                padding-bottom: 0.75rem;
                padding-top: 0.6rem;
            }
        }

        @media (max-width: 1024px) and (max-height: 680px) {
            #app-sidebar {
                padding-top: calc(0.75rem + env(safe-area-inset-top));
                padding-bottom: calc(0.85rem + env(safe-area-inset-bottom));
            }

            #app-sidebar .sidebar-brand-wrapper {
                margin-bottom: 0.35rem;
            }

            #app-sidebar nav {
                padding-bottom: 0.85rem;
            }

            #app-sidebar .sidebar-link {
                padding: 0.55rem 0.8rem;
            }
        }

        @media (min-width: 1025px) {
            .mobile-nav-trigger {
                display: none !important;
            }
        }
    </style>
    @stack('styles')
    @livewireStyles
</head>

<body class="app-shell antialiased bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100" data-guest-mode="{{ $guestMode ? '1' : '0' }}">
    <div id="app-shell" class="min-h-screen flex">
        @include($sidebarView ?? 'layouts.components.sidebar')
        <div class="sidebar-backdrop hidden lg:hidden" data-mobile-sidebar-backdrop></div>

        <div class="flex-1 flex flex-col w-full bg-gradient-to-br from-slate-50 via-white to-indigo-50/60 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900">
            <header id="app-header" class="app-header border-b border-slate-100/80 bg-white/90 backdrop-blur-xl dark:border-slate-900/70 dark:bg-slate-950/70">
                <div class="max-w-7xl mx-auto px-6 py-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                    <div class="min-w-0 space-y-1">
                        <div class="flex items-center gap-3 text-2xl font-semibold leading-snug text-slate-900 dark:text-white">
                            <button type="button"
                                class="mobile-nav-trigger inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-500/60"
                                aria-label="Buka menu"
                                data-mobile-sidebar-open>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            @hasSection('page_title')
                                @yield('page_title')
                            @else
                                @isset($header)
                                    {{ $header }}
                                @else
                                    Dashboard
                                @endisset
                            @endif
                            @if($guestMode)
                                <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-100">
                                    Mode tamu
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                            @hasSection('page_description')
                                @yield('page_description')
                            @else
                                Satu workspace untuk jadwal, keuangan, tugas, dan catatan kampus.
                            @endif
                        </p>
                    </div>
                    <div class="flex items-start md:items-center gap-3 flex-wrap justify-start md:justify-end">
                        @hasSection('page_actions')
                            <div class="flex items-center gap-2 flex-wrap justify-start md:justify-end">
                                @yield('page_actions')
                            </div>
                        @endif
                    </div>
                </div>

                @hasSection('page_toolbar')
                    <div class="border-t border-slate-100/70 dark:border-slate-900/60">
                        <div class="max-w-7xl mx-auto px-6 py-3">
                            <div class="page-toolbar">
                                @yield('page_toolbar')
                            </div>
                        </div>
                    </div>
                @endif
            </header>

            <main class="flex-1">
                <div class="px-6 py-8">
                    <div class="max-w-7xl mx-auto w-full">
                        <div class="page-stack">
                            @if (session('success'))
                                <div class="page-alert">
                                    <span class="page-alert-dot" aria-hidden="true"></span>
                                    <span>{{ session('success') }}</span>
                                </div>
                            @endif

                            @hasSection('content')
                                @yield('content')
                            @else
                                {{ $slot ?? '' }}
                            @endif
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        (() => {
            const storageKey = 'theme';
            const root = document.documentElement;

            const initThemeToggle = () => {
                const toggles = document.querySelectorAll('[data-theme-toggle]');
                if (!toggles.length) {
                    return;
                }

                let dimTimer;
                const body = document.body;

                const triggerDim = () => {
                    if (!body) {
                        return;
                    }
                    body.classList.add('theme-switching');
                    clearTimeout(dimTimer);
                    dimTimer = setTimeout(() => body.classList.remove('theme-switching'), 400);
                };

                const setTheme = (mode) => {
                    triggerDim();
                    const isDark = mode === 'dark';
                    root.classList.toggle('dark', isDark);
                    root.dataset.theme = isDark ? 'dark' : 'light';
                    try {
                        localStorage.setItem(storageKey, mode);
                    } catch (err) {
                        console.warn('Theme persistence issue', err);
                    }
                    toggles.forEach((btn) =>
                        btn.setAttribute('aria-pressed', isDark ? 'true' : 'false')
                    );
                };

                const currentMode = root.classList.contains('dark') ? 'dark' : 'light';
                toggles.forEach((btn) =>
                    btn.setAttribute('aria-pressed', currentMode === 'dark' ? 'true' : 'false')
                );

                toggles.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const nextMode = root.classList.contains('dark') ? 'light' : 'dark';
                        setTheme(nextMode);
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initThemeToggle);
            } else {
                initThemeToggle();
            }
        })();
    </script>
    <script>
        (() => {
            const storageKey = 'sidebar:collapsed';
            const root = document.documentElement;
            const toggles = document.querySelectorAll('[data-sidebar-toggle]');
            if (!toggles.length) {
                return;
            }

            const desktopQuery = window.matchMedia('(min-width: 1025px)');

            const readPreference = () => {
                try {
                    return localStorage.getItem(storageKey) === 'true';
                } catch (err) {
                    return false;
                }
            };

            const applyState = (collapsed, { persist = false } = {}) => {
                root.classList.toggle('sidebar-collapsed', collapsed);
                document.body.classList.toggle('sidebar-collapsed', collapsed);
                toggles.forEach((btn) =>
                    btn.setAttribute('aria-pressed', collapsed ? 'true' : 'false')
                );
                if (persist) {
                    try {
                        localStorage.setItem(storageKey, collapsed ? 'true' : 'false');
                    } catch (err) {
                        console.warn('Sidebar persistence issue', err);
                    }
                }
            };

            const setMobileOpen = (open) => {
                document.body.classList.toggle('sidebar-open', open);
                root.classList.toggle('sidebar-open', open);
                const backdrop = document.querySelector('[data-mobile-sidebar-backdrop]');
                if (backdrop) {
                    backdrop.classList.toggle('hidden', !open);
                }
            };

            const syncToViewport = () => {
                const collapsed = desktopQuery.matches
                    ? (root.classList.contains('sidebar-collapsed') || readPreference())
                    : false;
                applyState(collapsed, { persist: false });

                if (desktopQuery.matches) {
                    setMobileOpen(false);
                }
            };

            syncToViewport();

            toggles.forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!desktopQuery.matches) {
                        return;
                    }
                    const next = !root.classList.contains('sidebar-collapsed');
                    applyState(next, { persist: true });
                });
            });

            const mobileOpeners = document.querySelectorAll('[data-mobile-sidebar-open]');
            mobileOpeners.forEach((btn) => {
                btn.addEventListener('click', () => setMobileOpen(true));
            });

            const backdrop = document.querySelector('[data-mobile-sidebar-backdrop]');
            if (backdrop) {
                backdrop.addEventListener('click', () => setMobileOpen(false));
            }

            const handleViewportChange = () => syncToViewport();
            if (typeof desktopQuery.addEventListener === 'function') {
                desktopQuery.addEventListener('change', handleViewportChange);
            } else if (typeof desktopQuery.addListener === 'function') {
                desktopQuery.addListener(handleViewportChange);
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1025) {
                    setMobileOpen(false);
                }
            });

            document.querySelectorAll('#app-sidebar a.sidebar-link').forEach((link) => {
                link.addEventListener('click', () => setMobileOpen(false));
            });
        })();
    </script>
    <script>
        (() => {
            const header = document.getElementById('app-header');
            if (!header) return;

            let lastY = window.scrollY || 0;
            let ticking = false;
            let hidden = false;

            const updateHeaderState = () => {
                const currentY = window.scrollY || 0;
                const delta = currentY - lastY;
                const scrolledPast = currentY > 12;

                // Hide on scroll down; only show again when near top.
                if (delta > 6 && scrolledPast && !hidden) {
                    header.classList.add('is-hidden');
                    hidden = true;
                } else if (currentY <= 12 && hidden) {
                    header.classList.remove('is-hidden');
                    hidden = false;
                }

                header.classList.toggle('is-raised', scrolledPast && !hidden);
                lastY = currentY;
                ticking = false;
            };

            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(updateHeaderState);
                    ticking = true;
                }
            }, { passive: true });

            updateHeaderState();
        })();
    </script>

    @stack('scripts')
    @livewireScripts
</body>

</html>
