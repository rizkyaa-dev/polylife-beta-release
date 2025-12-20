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

<div>
    @if ($showSuccessModal)
        <div class="fixed inset-0 z-30 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-md rounded-[20px] border-2 border-[#2B2250]/10 bg-white p-6 shadow-[6px_6px_0_0_#2B2250] dark:border-white/15 dark:bg-[#0F0A21] dark:shadow-[6px_6px_0_0_rgba(8,5,18,0.95)]">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-[#8181FF] to-[#F49CC8] text-white shadow-lg">
                        ✓
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-[#2D2D3C] dark:text-white">Berhasil terdaftar</p>
                        <p class="text-sm text-[#6D6797] dark:text-[#C7C2EE]">Kami sudah mengirim email verifikasi. Silakan cek inbox (atau spam) lalu klik tautan verifikasi.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button
                        wire:click="closeSuccessModal"
                        class="rounded-[12px] border-2 border-[#2B2250] bg-[#8181FF] px-4 py-2 text-sm font-semibold text-white shadow-[4px_4px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[6px_6px_0_0_#2B2250] focus:outline-none focus:ring-2 focus:ring-[#F9A8D4] dark:border-[#6A5BFF] dark:bg-[#6A5BFF] dark:shadow-[4px_4px_0_0_rgba(106,91,255,0.65)]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-4 text-sm text-gray-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @elseif (str_starts_with((string) session('status'), 'verification-link-rate-limited'))
        @php
            $parts = explode(':', session('status'));
            $retryAfter = $parts[1] ?? 60;
        @endphp
        <div class="mb-4 font-medium text-sm text-rose-600">
            {{ __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $retryAfter]) }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <x-primary-button wire:click="sendVerification">
            {{ __('Resend Verification Email') }}
        </x-primary-button>

        <button wire:click="logout" type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            {{ __('Log Out') }}
        </button>
    </div>
</div>
