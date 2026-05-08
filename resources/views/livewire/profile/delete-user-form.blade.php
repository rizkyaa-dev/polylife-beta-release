<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header class="space-y-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-rose-500 dark:text-rose-300">Area berbahaya</p>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">
            Hapus akun
        </h2>

        <p class="text-sm text-gray-500 dark:text-slate-400">
            Setelah dihapus, akun dan data workspace terkait akan ikut terhapus permanen.
        </p>
    </header>

    <button type="button"
        class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-500"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Hapus akun</button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">
                Yakin ingin menghapus akun?
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                Masukkan password untuk mengonfirmasi penghapusan akun secara permanen.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Password" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="Password"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Batal
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    Hapus akun
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
