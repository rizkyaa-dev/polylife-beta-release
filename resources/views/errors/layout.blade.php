<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PolyLife | @yield('code', 'Error')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            const root = document.documentElement;
            if (!root) {
                return;
            }

            try {
                const storedTheme = window.localStorage.getItem('theme');
                if (storedTheme === 'light') {
                    root.classList.remove('dark');
                } else {
                    root.classList.add('dark');
                }
            } catch (error) {
                root.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="font-sans antialiased bg-[#FDF8FF] text-[#2D2D3C] dark:bg-[#0A0815] dark:text-[#F4F2FF]">
    @php
        $isAuthenticated = auth()->check();
        $homeRouteName = $isAuthenticated ? auth()->user()->defaultDashboardRouteName() : 'landing';
        $homeUrl = route($homeRouteName);
        $currentUrl = rtrim(url()->current(), '/');
        $previousUrl = rtrim((string) url()->previous(), '/');
        $appOrigin = rtrim(url('/'), '/');
        $isInternalPreviousUrl = $previousUrl !== ''
            && (
                $previousUrl === $appOrigin
                || str_starts_with($previousUrl, $appOrigin.'/')
                || str_starts_with($previousUrl, $appOrigin.'?')
            );
        $backUrl = ($isInternalPreviousUrl && $previousUrl !== $currentUrl) ? $previousUrl : $homeUrl;
    @endphp

    <div class="relative min-h-screen flex items-center justify-center px-4 py-12 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-[#FFF7FB] via-[#F7F4FF] to-[#FDF8FF] dark:from-[#05030a] dark:via-[#09041a] dark:to-[#120b2f]"></div>
        <div class="absolute inset-0 opacity-50" style="background-image: linear-gradient(transparent 31px, rgba(129,129,255,0.12) 32px), linear-gradient(90deg, transparent 31px, rgba(129,129,255,0.12) 32px); background-size: 32px 32px;"></div>
        <div class="absolute -top-24 right-10 h-64 w-64 bg-[#FFCCE1]/50 shadow-[12px_12px_0_0_rgba(255,255,255,0.6)] dark:bg-[#37205D]/70 dark:shadow-[12px_12px_0_0_rgba(24,12,44,0.8)]"></div>
        <div class="absolute -bottom-28 left-4 h-72 w-72 bg-[#C9E5FF]/50 shadow-[-12px_-12px_0_0_rgba(255,255,255,0.4)] dark:bg-[#1D244F]/80 dark:shadow-[-12px_-12px_0_0_rgba(8,8,20,0.7)]"></div>

        <div class="relative w-full max-w-3xl">
            <div class="relative rounded-[28px] border-4 border-[#8181FF] bg-[#FDFBFF]/95 p-6 sm:p-10 shadow-[12px_12px_0_0_#C5D4FF] dark:border-[#6A5BFF] dark:bg-[#15102B]/95 dark:shadow-[12px_12px_0_0_rgba(9,5,24,0.9)]">
                <div class="absolute -top-6 right-12 h-12 w-12 border-4 border-[#2B2250] bg-[#F49CC8] shadow-[6px_6px_0_0_#2B2250] dark:border-[#120B26] dark:bg-[#6A5BFF]/60 dark:shadow-[6px_6px_0_0_rgba(7,4,19,0.9)]"></div>

                <div class="space-y-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 grid place-items-center rounded-md border-4 border-[#2B2250] bg-[#8181FF] text-white text-xl font-black shadow-[6px_6px_0_0_#2B2250] dark:border-[#0B0718] dark:bg-[#6A5BFF] dark:shadow-[6px_6px_0_0_rgba(7,4,19,0.9)]">
                                PL
                            </div>
                            <div>
                                <p class="text-lg font-black tracking-wide text-[#2D2D3C] dark:text-white">PolyLife</p>
                                <p class="text-[11px] uppercase tracking-[0.32em] text-[#6D6797] dark:text-[#B6B0EC]">error page</p>
                            </div>
                        </div>
                        <span class="rounded-[14px] border-2 border-[#2B2250]/20 bg-white px-4 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-[#6D6797] shadow-[4px_4px_0_0_rgba(197,212,255,0.9)] dark:border-white/20 dark:bg-[#140F2B] dark:text-[#B6B0EC] dark:shadow-[4px_4px_0_0_rgba(11,6,22,0.9)]">
                            @yield('code')
                        </span>
                    </div>

                    <div class="space-y-4">
                        <h1 class="text-3xl font-bold leading-tight text-[#2D2D3C] dark:text-white">
                            @yield('title')
                        </h1>
                        <p class="text-base text-[#6D6797] dark:text-[#C7C2EE]">
                            @yield('message')
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $backUrl }}"
                            class="px-5 py-3 rounded-[18px] border-2 border-[#2B2250] bg-[#8181FF] text-white text-sm font-semibold uppercase tracking-wide shadow-[6px_6px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[8px_8px_0_0_#2B2250] dark:border-[#0B0718] dark:bg-[#6A5BFF] dark:shadow-[6px_6px_0_0_rgba(7,4,19,0.9)]">
                            Kembali
                        </a>
                        <a href="{{ $homeUrl }}"
                            class="px-5 py-3 rounded-[18px] border-2 border-[#2B2250] bg-[#FFFFFF] text-[#2D2D3C] text-sm font-semibold uppercase tracking-wide shadow-[6px_6px_0_0_#C5D4FF] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[8px_8px_0_0_#C5D4FF] dark:border-[#6A5BFF]/60 dark:bg-[#1B143A] dark:text-slate-100 dark:hover:bg-[#251B52] dark:shadow-[6px_6px_0_0_rgba(7,4,19,0.9)]">
                            Ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
