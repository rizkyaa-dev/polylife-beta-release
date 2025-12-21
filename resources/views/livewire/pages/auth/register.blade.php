<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        
        // Map 'name' to the correct database fields
        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        $user = User::create($userData);

        try {
            event(new Registered($user));
            Session::flash('status', 'Akun berhasil dibuat. Silakan cek email untuk verifikasi, lalu login kembali.');
        } catch (\Throwable $e) {
            report($e);
            Session::flash('status', 'Akun berhasil dibuat. Email verifikasi belum dapat dikirim. Silakan login dan kirim ulang verifikasi.');
        }

        $this->redirect(route('login', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-10">
    <div class="space-y-3">
        <div class="inline-flex items-center gap-2 rounded-[14px] border-2 border-[#2B2250]/20 bg-white px-4 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-[#6D6797] dark:border-white/30 dark:bg-[#140F2B] dark:text-[#B6B0EC]">
            <span class="h-2 w-2 bg-[#F49CC8] dark:bg-[#B598FF]"></span>
            Daftar
        </div>
        <h2 class="text-3xl font-bold text-[#2D2D3C] dark:text-white">Bangun akun PolyLife kamu</h2>
        <p class="text-base text-[#6D6797] dark:text-[#C7C2EE]">
            Atur tugas, catatan, dan arus keuangan dalam satu ruang kerja yang terorganisir!!
        </p>
    </div>

    <form wire:submit="register" class="space-y-6">
        <div class="space-y-2">
            <label for="name" class="text-sm font-semibold text-[#4C4C63] dark:text-[#D7D3FF]">{{ __('Name') }}</label>
            <input
                wire:model="name"
                id="name"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="name"
                placeholder="Nama lengkap"
                class="w-full rounded-[18px] border-2 border-[#8181FF]/40 bg-[#F6F4FF] px-4 py-3 text-base font-medium text-[#2D2D3C] placeholder:text-[#A7A6C9] shadow-[4px_4px_0_0_#C5D4FF] focus:border-[#8181FF] focus:outline-none focus:ring-0 transition dark:border-[#6A5BFF]/70 dark:bg-[#120C26] dark:text-white dark:placeholder:text-[#8A83C5] dark:shadow-[4px_4px_0_0_rgba(11,6,22,0.9)]" />
            @error('name')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="email" class="text-sm font-semibold text-[#4C4C63] dark:text-[#D7D3FF]">{{ __('Email') }}</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                name="email"
                required
                autocomplete="username"
                placeholder="nama@kampus.ac.id"
                class="w-full rounded-[18px] border-2 border-[#8181FF]/40 bg-[#F6F4FF] px-4 py-3 text-base font-medium text-[#2D2D3C] placeholder:text-[#A7A6C9] shadow-[4px_4px_0_0_#C5D4FF] focus:border-[#8181FF] focus:outline-none focus:ring-0 transition dark:border-[#6A5BFF]/70 dark:bg-[#120C26] dark:text-white dark:placeholder:text-[#8A83C5] dark:shadow-[4px_4px_0_0_rgba(11,6,22,0.9)]" />
            @error('email')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="password" class="text-sm font-semibold text-[#4C4C63] dark:text-[#D7D3FF]">{{ __('Password') }}</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Minimal 8 karakter"
                class="w-full rounded-[18px] border-2 border-[#8181FF]/40 bg-[#F6F4FF] px-4 py-3 text-base font-medium text-[#2D2D3C] placeholder:text-[#A7A6C9] shadow-[4px_4px_0_0_#C5D4FF] focus:border-[#8181FF] focus:outline-none focus:ring-0 transition dark:border-[#6A5BFF]/70 dark:bg-[#120C26] dark:text-white dark:placeholder:text-[#8A83C5] dark:shadow-[4px_4px_0_0_rgba(11,6,22,0.9)]" />
            @error('password')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="password_confirmation" class="text-sm font-semibold text-[#4C4C63] dark:text-[#D7D3FF]">{{ __('Confirm Password') }}</label>
            <input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Ulangi password"
                class="w-full rounded-[18px] border-2 border-[#8181FF]/40 bg-[#F6F4FF] px-4 py-3 text-base font-medium text-[#2D2D3C] placeholder:text-[#A7A6C9] shadow-[4px_4px_0_0_#C5D4FF] focus:border-[#8181FF] focus:outline-none focus:ring-0 transition dark:border-[#6A5BFF]/70 dark:bg-[#120C26] dark:text-white dark:placeholder:text-[#8A83C5] dark:shadow-[4px_4px_0_0_rgba(11,6,22,0.9)]" />
            @error('password_confirmation')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-[18px] border-2 border-[#2B2250] bg-[#8181FF] px-4 py-3 text-base font-semibold uppercase tracking-wide text-white shadow-[5px_5px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[7px_7px_0_0_#2B2250] focus:outline-none focus:ring-2 focus:ring-[#F9A8D4] dark:border-[#0B0718] dark:bg-[#6A5BFF] dark:shadow-[5px_5px_0_0_rgba(5,3,12,0.9)] dark:focus:ring-[#F49CC8]/60">
            {{ __('Register') }}
        </button>
    </form>

    <p class="text-center text-sm text-[#6D6797] dark:text-[#C7C2EE]">
        {{ __('Already registered?') }}
        <a
            href="{{ route('login') }}"
            wire:navigate
            class="font-semibold text-[#8181FF] underline decoration-dashed underline-offset-4 hover:text-[#5A57C9] dark:text-[#B598FF] dark:hover:text-[#E1D6FF]">
            {{ __('Log in') }}
        </a>
    </p>
</div>
