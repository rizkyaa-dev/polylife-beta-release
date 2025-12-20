<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            (function () {
                const root = document.documentElement;
                if (!root) {
                    return;
                }

                const prefersDark = () => window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

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
        <div class="relative min-h-screen flex items-center justify-center px-4 py-12 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-[#FFF7FB] via-[#F7F4FF] to-[#FDF8FF] dark:from-[#05030a] dark:via-[#09041a] dark:to-[#120b2f]"></div>
            <div class="absolute inset-0 opacity-50" style="background-image: linear-gradient(transparent 31px, rgba(129,129,255,0.12) 32px), linear-gradient(90deg, transparent 31px, rgba(129,129,255,0.12) 32px); background-size: 32px 32px;"></div>
            <div class="absolute -top-24 right-10 h-64 w-64 bg-[#FFCCE1]/50 shadow-[12px_12px_0_0_rgba(255,255,255,0.6)] dark:bg-[#37205D]/70 dark:shadow-[12px_12px_0_0_rgba(24,12,44,0.8)]"></div>
            <div class="absolute -bottom-28 left-4 h-72 w-72 bg-[#C9E5FF]/50 shadow-[-12px_-12px_0_0_rgba(255,255,255,0.4)] dark:bg-[#1D244F]/80 dark:shadow-[-12px_-12px_0_0_rgba(8,8,20,0.7)]"></div>

            <div class="relative w-full max-w-3xl">
                <div class="relative rounded-[28px] border-4 border-[#8181FF] bg-[#FDFBFF]/95 p-6 sm:p-10 shadow-[12px_12px_0_0_#C5D4FF] dark:border-[#6A5BFF] dark:bg-[#15102B]/95 dark:shadow-[12px_12px_0_0_rgba(9,5,24,0.9)]">
                    <div class="absolute -top-6 right-12 h-12 w-12 border-4 border-[#2B2250] bg-[#F49CC8] shadow-[6px_6px_0_0_#2B2250] dark:border-[#120B26] dark:bg-[#6A5BFF]/60 dark:shadow-[6px_6px_0_0_rgba(7,4,19,0.9)]"></div>
                    <button
                        id="theme-toggle"
                        type="button"
                        class="absolute -bottom-8 left-10 flex h-16 w-16 items-center justify-center border-4 border-[#2B2250] bg-[#B5F1FF] text-[#2B2250] shadow-[6px_6px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#F9A8D4] focus-visible:ring-offset-2 focus-visible:ring-offset-[#FDFBFF] dark:border-[#120B26] dark:bg-[#F49CC8]/50 dark:text-white dark:shadow-[6px_6px_0_0_rgba(7,4,19,0.85)] dark:focus-visible:ring-[#7EE5FF] dark:focus-visible:ring-offset-[#15102B]"
                        aria-label="Toggle color mode"
                        aria-pressed="false">
                        <span class="sr-only" data-label="text">Mode gelap</span>
                        <svg data-icon="sun" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                        </svg>
                        <svg data-icon="moon" class="hidden h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15.25A6.75 6.75 0 0112.75 7 6.75 6.75 0 1021 15.25z" />
                        </svg>
                    </button>
                    <div class="relative">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
        @php
            $rateLimitMessage = session('register_rate_limit_message') ?? ($errors->first('rate_limit') ?? null);
            $rateLimitRetry = session('register_rate_limit_retry');
        @endphp
        @if($rateLimitMessage)
            @include('auth.partials.register-rate-limit', [
                'message' => $rateLimitMessage,
                'retryAfter' => $rateLimitRetry,
            ])
        @endif
    </body>
</html>
