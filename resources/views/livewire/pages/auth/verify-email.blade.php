<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public bool $showSuccessModal = false;

    public function mount(): void
    {
        $this->showSuccessModal = Session::has('registration_success');
    }

    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        $rateLimiterKey = 'send-verification:'.Auth::id();

        if (RateLimiter::tooManyAttempts($rateLimiterKey, 5)) {
            Session::flash('status', 'verification-link-rate-limited:'.RateLimiter::availableIn($rateLimiterKey));

            return;
        }

        RateLimiter::hit($rateLimiterKey, 60);

        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('workspace.home', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function closeSuccessModal(): void
    {
        $this->showSuccessModal = false;
        Session::forget('registration_success');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-10">
    @if ($showSuccessModal)
        <div class="fixed inset-0 z-30 flex items-center justify-center bg-[#09071A]/65 px-4">
            <div class="w-full max-w-md rounded-[20px] border-2 border-[#2B2250]/10 bg-white p-6 shadow-[6px_6px_0_0_#2B2250] dark:border-white/15 dark:bg-[#0F0A21] dark:shadow-[6px_6px_0_0_rgba(8,5,18,0.95)]">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-[#8181FF] to-[#F49CC8] text-white shadow-[4px_4px_0_0_#2B2250] dark:shadow-[4px_4px_0_0_rgba(7,4,19,0.85)]">
                        <svg viewBox="0 0 20 20" fill="none" class="h-6 w-6" aria-hidden="true">
                            <path d="M5 10.5L8.2 13.7L15 6.9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-[#2D2D3C] dark:text-white">Berhasil terdaftar</p>
                        <p class="text-sm text-[#6D6797] dark:text-[#C7C2EE]">Kami sudah mengirim email verifikasi. Silakan cek inbox (atau spam) lalu klik tautan verifikasi.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button
                        wire:click="closeSuccessModal"
                        type="button"
                        class="rounded-[12px] border-2 border-[#2B2250] bg-[#8181FF] px-4 py-2 text-sm font-semibold text-white shadow-[4px_4px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[6px_6px_0_0_#2B2250] focus:outline-none focus:ring-2 focus:ring-[#F9A8D4] dark:border-[#6A5BFF] dark:bg-[#6A5BFF] dark:shadow-[4px_4px_0_0_rgba(106,91,255,0.65)]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-3">
        <div class="inline-flex items-center gap-2 rounded-[14px] border-2 border-[#2B2250]/20 bg-white px-4 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-[#6D6797] dark:border-white/30 dark:bg-[#140F2B] dark:text-[#B6B0EC]">
            <span class="h-2 w-2 bg-[#6AE4C8] dark:bg-[#9AF2DD]"></span>
            Verifikasi
        </div>
        <h2 class="text-3xl font-bold text-[#2D2D3C] dark:text-white">Aktifkan email akun PolyLife</h2>
        <p class="text-base text-[#6D6797] dark:text-[#C7C2EE]">
            Kami sudah mengirim link verifikasi ke email kamu. Buka inbox atau folder spam, lalu klik tombol verifikasi di email tersebut.
        </p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="rounded-[18px] border-2 border-[#6AE4C8] bg-[#E8FFF7] px-4 py-3 text-sm font-semibold text-[#189570] shadow-[4px_4px_0_0_#B8FFE7] dark:border-[#2FD3A6]/60 dark:bg-[#052926] dark:text-[#8CECD2] dark:shadow-[4px_4px_0_0_rgba(5,20,20,0.8)]">
            Link verifikasi baru sudah dikirim. Cek inbox kamu sekarang.
        </div>
    @elseif (str_starts_with((string) session('status'), 'verification-link-rate-limited'))
        @php
            $parts = explode(':', session('status'));
            $retryAfter = $parts[1] ?? 60;
        @endphp
        <div class="rounded-[18px] border-2 border-[#F49CC8]/70 bg-[#FFF0F7] px-4 py-3 text-sm font-semibold text-[#A63A71] shadow-[4px_4px_0_0_#FFD4E8] dark:border-[#DB6CA4]/70 dark:bg-[#2A1023] dark:text-[#FFC8E5] dark:shadow-[4px_4px_0_0_rgba(45,14,34,0.9)]">
            Terlalu sering kirim ulang. Coba lagi dalam {{ $retryAfter }} detik.
        </div>
    @endif

    <div class="rounded-[20px] border-2 border-[#2B2250]/10 bg-white/95 p-5 shadow-[6px_6px_0_0_#C5D4FF] dark:border-white/15 dark:bg-[#100A24]/95 dark:shadow-[6px_6px_0_0_rgba(8,5,20,0.95)]">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[#6D6797] dark:text-[#B6B0EC]">Checklist cepat</p>
        <ul class="mt-3 space-y-2 text-sm text-[#4C4C63] dark:text-[#D7D3FF]">
            <li class="flex items-start gap-2">
                <span class="mt-1 h-2 w-2 bg-[#8181FF]"></span>
                Pastikan email terdaftar sudah benar.
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-1 h-2 w-2 bg-[#8181FF]"></span>
                Cek folder spam atau promosi jika email belum terlihat.
            </li>
            <li class="flex items-start gap-2">
                <span class="mt-1 h-2 w-2 bg-[#8181FF]"></span>
                Gunakan tombol kirim ulang jika link belum masuk.
            </li>
        </ul>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <button
            wire:click="sendVerification"
            type="button"
            class="inline-flex items-center justify-center rounded-[18px] border-2 border-[#2B2250] bg-[#8181FF] px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-[5px_5px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[7px_7px_0_0_#2B2250] focus:outline-none focus:ring-2 focus:ring-[#F9A8D4] dark:border-[#0B0718] dark:bg-[#6A5BFF] dark:shadow-[5px_5px_0_0_rgba(5,3,12,0.9)] dark:focus:ring-[#F49CC8]/60">
            Kirim Ulang Email Verifikasi
        </button>

        <button
            wire:click="logout"
            type="button"
            class="inline-flex items-center justify-center rounded-[14px] border-2 border-[#2B2250]/25 bg-white px-4 py-2 text-sm font-semibold text-[#4C4C63] transition hover:border-[#8181FF] hover:text-[#5A57C9] focus:outline-none focus:ring-2 focus:ring-[#8181FF]/40 dark:border-white/30 dark:bg-[#15102B] dark:text-[#D7D3FF] dark:hover:border-[#B598FF] dark:hover:text-[#E1D6FF]">
            Keluar
        </button>
    </div>
</div>
