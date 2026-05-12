<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function mount(): void
    {
        $retryUntil = (int) session('login_throttle_until', 0);
        $seconds = max(0, $retryUntil - time());

        if ($seconds > 0) {
            $this->form->retryAfterSeconds = $seconds;

            $this->addError('form.email', trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]));

            return;
        }

        session()->forget('login_throttle_until');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $user = Auth::user();

        if ($user && ! $user->isActiveAccount()) {
            $this->redirect(route('account.banned', absolute: false), navigate: true);

            return;
        }

        $defaultRouteName = $user ? $user->defaultDashboardRouteName() : 'workspace.home';
        $defaultRoute = route($defaultRouteName, absolute: false);

        $this->redirectIntended(default: $defaultRoute, navigate: true);
    }
}; ?>

<div class="space-y-10">
    <div class="space-y-3">
        <div class="inline-flex items-center gap-2 rounded-[14px] border-2 border-[#2B2250]/20 bg-white px-4 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-[#6D6797] dark:border-white/30 dark:bg-[#140F2B] dark:text-[#B6B0EC]">
            <span class="h-2 w-2 bg-[#8181FF] dark:bg-[#B598FF]"></span>
            Masuk
        </div>
        <h2 class="text-3xl font-bold text-[#2D2D3C] dark:text-white">Selamat datang kembali di PolyLife</h2>
        <p class="text-base text-[#6D6797] dark:text-[#C7C2EE]">
            Pantau jadwal kuliah, IPK, dan pengingat penting dari satu tempat
        </p>
    </div>

    <x-auth-session-status
        class="rounded-[18px] border-2 border-[#6AE4C8] bg-[#E8FFF7] px-4 py-3 text-sm font-semibold text-[#189570] shadow-[4px_4px_0_0_#B8FFE7] dark:border-[#2FD3A6]/60 dark:bg-[#052926] dark:text-[#8CECD2] dark:shadow-[4px_4px_0_0_rgba(5,20,20,0.8)]"
        :status="session('status')" />

    <form wire:submit="login" class="space-y-6" data-login-form>
        <div class="space-y-2">
            <label for="email" class="text-sm font-semibold text-[#4C4C63] dark:text-[#D7D3FF]">{{ __('Email') }}</label>
            <input
                wire:model="form.email"
                id="email"
                type="email"
                name="email"
                autofocus
                autocomplete="username"
                placeholder="nama@kampus.ac.id"
                data-login-email
                class="auth-input" />
            @error('form.email')
                <p class="text-sm text-rose-500"
                    @if ($form->retryAfterSeconds > 0)
                        data-login-throttle-message
                        data-login-throttle-seconds="{{ $form->retryAfterSeconds }}"
                        data-login-throttle-template="{{ trans('auth.throttle', ['seconds' => '__SECONDS__', 'minutes' => '__MINUTES__']) }}"
                        data-login-throttle-ready="{{ __('You may try again now.') }}"
                    @endif>
                    {{ $message }}
                </p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="password" class="text-sm font-semibold text-[#4C4C63] dark:text-[#D7D3FF]">{{ __('Password') }}</label>
            <input
                wire:model="form.password"
                id="password"
                type="password"
                name="password"
                autocomplete="current-password"
                placeholder="••••••••"
                data-login-password
                class="auth-input" />
            @error('form.password')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember" class="inline-flex items-center gap-3 text-sm font-semibold text-[#4C4C63] dark:text-[#D7D3FF]">
                <input
                    wire:model="form.remember"
                    id="remember"
                    type="checkbox"
                    name="remember"
                    class="h-4 w-4 rounded-sm border-2 border-[#2B2250]/30 text-[#8181FF] focus:ring-[#8181FF] dark:border-white/40 dark:bg-transparent dark:text-[#B598FF]" />
                {{ __('Remember me') }}
            </label>

            @if (Route::has('password.request'))
                <a
                    href="{{ route('password.request') }}"
                    wire:navigate
                    class="text-sm font-semibold text-[#8181FF] underline decoration-dashed underline-offset-4 hover:text-[#5A57C9] dark:text-[#B598FF] dark:hover:text-[#E1D6FF]">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-[18px] border-2 border-[#2B2250] bg-[#8181FF] px-4 py-3 text-base font-semibold uppercase tracking-wide text-white shadow-[5px_5px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[7px_7px_0_0_#2B2250] focus:outline-none focus:ring-2 focus:ring-[#F9A8D4] dark:border-[#0B0718] dark:bg-[#6A5BFF] dark:shadow-[5px_5px_0_0_rgba(5,3,12,0.9)] dark:focus:ring-[#F49CC8]/60">
            {{ __('Log in') }}
        </button>
    </form>

    <p class="text-center text-sm text-[#6D6797] dark:text-[#C7C2EE]">
        {{ __("Don't have an account?") }}
        <a
            href="{{ route('register') }}"
            wire:navigate
            class="font-semibold text-[#8181FF] underline decoration-dashed underline-offset-4 hover:text-[#5A57C9] dark:text-[#B598FF] dark:hover:text-[#E1D6FF]">
            {{ __('Register') }}
        </a>
    </p>
</div>

@script
<script>
    const bindLoginFocusGuard = () => {
        const form = document.querySelector('[data-login-form]');

        if (!form || form.dataset.focusGuardBound === 'true') {
            return;
        }

        form.dataset.focusGuardBound = 'true';

        form.addEventListener('submit', (event) => {
            const email = form.querySelector('[data-login-email]');
            const password = form.querySelector('[data-login-password]');

            if (!email || !password) {
                return;
            }

            if (email.value.trim() !== '' && password.value === '') {
                event.preventDefault();
                event.stopImmediatePropagation();
                password.focus({ preventScroll: true });
            }
        }, true);
    };

    bindLoginFocusGuard();
    document.addEventListener('livewire:navigated', bindLoginFocusGuard);

    const startLoginThrottleCountdown = (message) => {
            const template = message.dataset.loginThrottleTemplate || '';
            const readyText = message.dataset.loginThrottleReady || '';
        let endsAt = Number.parseInt(message.dataset.loginThrottleEndsAt || '0', 10);

        if (!Number.isFinite(endsAt) || endsAt <= 0) {
            const seconds = Number.parseInt(message.dataset.loginThrottleSeconds || '0', 10);

            if (!Number.isFinite(seconds) || seconds <= 0) {
                return false;
            }

            endsAt = Date.now() + (seconds * 1000);
            message.dataset.loginThrottleEndsAt = String(endsAt);
        }

        if (!template) {
            return false;
        }

        const existingInterval = Number.parseInt(message.dataset.countdownInterval || '0', 10);
        if (Number.isFinite(existingInterval) && existingInterval > 0) {
            window.clearInterval(existingInterval);
        }

            const render = () => {
            const seconds = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));

                if (seconds <= 0) {
                    message.textContent = readyText;
                return 0;
                }

                message.textContent = template
                    .replace('__SECONDS__', String(seconds))
                    .replace('__MINUTES__', String(Math.ceil(seconds / 60)));

            return seconds;
            };

        if (render() <= 0) {
            delete message.dataset.countdownInterval;

            return true;
        }

            const interval = window.setInterval(() => {
            const remaining = render();

            if (remaining <= 0) {
                    window.clearInterval(interval);
                delete message.dataset.countdownInterval;
                }
            }, 1000);

        message.dataset.countdownInterval = String(interval);

        return true;
    };

    const bindLoginThrottleCountdown = () => {
        document.querySelectorAll('[data-login-throttle-message]').forEach((message) => {
            startLoginThrottleCountdown(message);
        });
    };

    bindLoginThrottleCountdown();

    if (!window.__polylifeLoginThrottleCountdownBound) {
        window.__polylifeLoginThrottleCountdownBound = true;
        document.addEventListener('livewire:navigated', bindLoginThrottleCountdown);
        window.addEventListener('pageshow', bindLoginThrottleCountdown);
        window.addEventListener('focus', bindLoginThrottleCountdown);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                bindLoginThrottleCountdown();
            }
        });
    }

    if (window.Livewire?.hook) {
        window.Livewire.hook('morph.updated', () => {
            bindLoginFocusGuard();
            bindLoginThrottleCountdown();
        });
    }
</script>
@endscript
