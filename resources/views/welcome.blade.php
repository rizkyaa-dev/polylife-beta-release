<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PolyLife | Workspace Kampus</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        const root = document.documentElement;
        root.classList.remove('dark');
        document.body?.classList.remove('dark');
        root.dataset.theme = 'light';
        try {
            localStorage.setItem('theme', 'light');
        } catch (e) {
            console.warn('Theme storage unavailable', e);
        }
    </script>
</head>
<body class="antialiased bg-[#FDF8FF] text-[#2D2D3C]">
    <div class="relative min-h-screen flex items-center justify-center px-4 py-12 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-[#FFF7FB] via-[#F7F4FF] to-[#FDF8FF]"></div>
        <div class="absolute inset-0 opacity-50"
            style="background-image: linear-gradient(transparent 31px, rgba(129,129,255,0.12) 32px), linear-gradient(90deg, transparent 31px, rgba(129,129,255,0.12) 32px); background-size: 32px 32px;">
        </div>
        <div class="absolute -top-24 right-10 h-64 w-64 bg-[#FFCCE1]/50 shadow-[12px_12px_0_0_rgba(255,255,255,0.6)]"></div>
        <div class="absolute -bottom-28 left-4 h-72 w-72 bg-[#C9E5FF]/50 shadow-[-12px_-12px_0_0_rgba(255,255,255,0.4)]"></div>

        <div class="relative w-full max-w-3xl">
            <div
                class="relative rounded-[28px] border-4 border-[#8181FF] bg-[#FDFBFF]/95 p-6 sm:p-10 shadow-[12px_12px_0_0_#C5D4FF]">
                <div class="absolute -top-6 right-12 h-12 w-12 border-4 border-[#2B2250] bg-[#F49CC8] shadow-[6px_6px_0_0_#2B2250]"></div>

                <div class="space-y-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="h-12 w-12 grid place-items-center rounded-md border-4 border-[#2B2250] bg-[#8181FF] text-white text-xl font-black shadow-[6px_6px_0_0_#2B2250]">
                                PL
                            </div>
                            <div>
                                <p class="text-lg font-black tracking-wide text-[#2D2D3C]">PolyLife</p>
                                <p class="text-[11px] uppercase tracking-[0.32em] text-[#6D6797]">workspace</p>
                            </div>
                        </div>
                        <span
                            class="rounded-[14px] border-2 border-[#2B2250]/20 bg-white px-4 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-[#6D6797] shadow-[4px_4px_0_0_rgba(197,212,255,0.9)]">
                            hello mate!!
                        </span>
                    </div>

                    <div class="space-y-4">
                        <h1 class="text-3xl font-bold leading-tight text-[#2D2D3C]">
                            Mulai dari satu layar yang sama dengan Login, Register, atau Mode Tamu.
                        </h1>
                        <p class="text-base text-[#6D6797]">
                            pilih tombolnya.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('workspace.home') }}"
                                    class="px-5 py-3 rounded-[18px] border-2 border-[#2B2250] bg-[#8181FF] text-white text-sm font-semibold uppercase tracking-wide shadow-[6px_6px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[8px_8px_0_0_#2B2250]">
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}"
                                    class="px-5 py-3 rounded-[18px] border-2 border-[#2B2250] bg-[#8181FF] text-white text-sm font-semibold uppercase tracking-wide shadow-[6px_6px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[8px_8px_0_0_#2B2250]">
                                    Login
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}"
                                        class="px-5 py-3 rounded-[18px] border-2 border-[#2B2250] bg-white text-[#2D2D3C] text-sm font-semibold uppercase tracking-wide shadow-[6px_6px_0_0_#C5D4FF] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[8px_8px_0_0_#C5D4FF]">
                                        Register
                                    </a>
                                @endif
                            @endauth
                        @endif
                        <a href="{{ route('guest.home') }}"
                            class="px-5 py-3 rounded-[18px] border-2 border-[#2B2250] bg-[#B5F1FF] text-[#2B2250] text-sm font-semibold uppercase tracking-wide shadow-[6px_6px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[8px_8px_0_0_#2B2250]">
                            Mode tamu
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
