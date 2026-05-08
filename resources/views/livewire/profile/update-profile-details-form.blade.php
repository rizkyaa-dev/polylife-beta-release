<?php

use App\Services\ProfileAvatarImageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $display_name = '';
    public string $bio = '';
    public string $phone = '';
    public string $date_of_birth = '';
    public string $gender = '';
    public string $location = '';
    public string $theme_preference = 'system';
    public string $timezone = 'Asia/Jakarta';
    public string $locale = 'id';
    public ?string $avatarUrl = null;
    public $avatar = null;

    public function mount(): void
    {
        $profile = Auth::user()->profile;

        $this->display_name = (string) ($profile?->display_name ?? '');
        $this->bio = (string) ($profile?->bio ?? '');
        $this->phone = (string) ($profile?->phone ?? '');
        $this->date_of_birth = $profile?->date_of_birth?->toDateString() ?? '';
        $this->gender = (string) ($profile?->gender ?? '');
        $this->location = (string) ($profile?->location ?? '');
        $this->theme_preference = (string) ($profile?->theme_preference ?? 'system');
        $this->timezone = (string) ($profile?->timezone ?? 'Asia/Jakarta');
        $this->locale = (string) ($profile?->locale ?? 'id');
        $this->avatarUrl = $profile?->avatar_url;
    }

    public function updatedAvatar(): void
    {
        $this->validateOnly('avatar', [
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
    }

    public function updateProfileDetails(): void
    {
        $validated = $this->validate([
            'display_name' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'location' => ['nullable', 'string', 'max:120'],
            'theme_preference' => ['required', Rule::in(['system', 'light', 'dark'])],
            'timezone' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', Rule::in(['id', 'en'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = Auth::user();
        $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);

        if ($this->avatar) {
            try {
                $optimizedAvatar = app(ProfileAvatarImageService::class)->optimize($this->avatar);
            } catch (\RuntimeException $exception) {
                $this->addError('avatar', $exception->getMessage());

                return;
            }

            $user->profileAvatar()->updateOrCreate(
                ['user_id' => $user->id],
                $optimizedAvatar
            );

            if ($profile->avatar_path) {
                Storage::disk('public')->delete($profile->avatar_path);
                $profile->avatar_path = null;
            }
        }

        $profile->fill([
            'display_name' => $this->nullableString($validated['display_name'] ?? null),
            'bio' => $this->nullableString($validated['bio'] ?? null),
            'phone' => $this->nullableString($validated['phone'] ?? null),
            'date_of_birth' => $validated['date_of_birth'] ?: null,
            'gender' => $validated['gender'] ?: null,
            'location' => $this->nullableString($validated['location'] ?? null),
            'theme_preference' => $validated['theme_preference'],
            'timezone' => $this->nullableString($validated['timezone'] ?? null),
            'locale' => $validated['locale'] ?: null,
            'preferences' => $profile->preferences ?? [],
        ]);

        $profile->save();

        $this->reset('avatar');
        $this->avatarUrl = $profile->avatar_url;

        $this->dispatch('profile-details-updated');
        $this->dispatch('profile-theme-updated', theme: $profile->theme_preference);
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();
        $profile = $user->profile;
        $user->profileAvatar()->delete();

        if (! $profile || ! $profile->avatar_path) {
            $this->reset('avatar');
            $this->avatarUrl = null;
            $this->dispatch('profile-details-updated');

            return;
        }

        Storage::disk('public')->delete($profile->avatar_path);
        $profile->forceFill(['avatar_path' => null])->save();

        $this->reset('avatar');
        $this->avatarUrl = null;

        $this->dispatch('profile-details-updated');
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}; ?>

<section>
    <header class="space-y-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Profil opsional</p>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Tampilan dan identitas workspace</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Data ini tidak mempengaruhi login. Gunakan untuk foto profil, nama tampilan, dan preferensi antarmuka.
        </p>
    </header>

    <form wire:submit="updateProfileDetails" class="mt-6 space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-3xl border border-indigo-100 bg-indigo-50 text-2xl font-bold text-indigo-700 shadow-sm dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-100">
                @if ($avatar)
                    <img src="{{ $avatar->temporaryUrl() }}" alt="Preview foto profil" class="h-full w-full object-cover">
                @elseif ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="Foto profil" class="h-full w-full object-cover">
                @else
                    <div class="grid h-full w-full place-items-center">
                        {{ mb_strtoupper(mb_substr(trim($display_name) !== '' ? $display_name : Auth::user()->name, 0, 1)) }}
                    </div>
                @endif
            </div>
            <div class="flex-1 space-y-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">Foto profil</p>
                    <p class="text-xs text-gray-500 dark:text-slate-400">PNG, JPG, atau WebP. Maksimal 2 MB.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <label for="profile_avatar"
                           class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        Pilih foto
                    </label>
                    <input id="profile_avatar" type="file" wire:model="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only">
                    @if ($avatarUrl)
                        <button type="button"
                                wire:click="removeAvatar"
                                class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-200 dark:hover:bg-rose-500/10">
                            Hapus foto
                        </button>
                    @endif
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
                <div wire:loading wire:target="avatar" class="text-xs font-medium text-indigo-500 dark:text-indigo-300">
                    Mengunggah preview...
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <x-input-label for="display_name" value="Nama tampilan" />
                <x-text-input wire:model="display_name" id="display_name" type="text" class="mt-1 block w-full" maxlength="100" autocomplete="nickname" />
                <x-input-error class="mt-2" :messages="$errors->get('display_name')" />
            </div>

            <div>
                <x-input-label for="location" value="Lokasi" />
                <x-text-input wire:model="location" id="location" type="text" class="mt-1 block w-full" maxlength="120" placeholder="Kota, kampus, atau area" />
                <x-input-error class="mt-2" :messages="$errors->get('location')" />
            </div>

            <div>
                <x-input-label for="phone" value="Nomor kontak" />
                <x-text-input wire:model="phone" id="phone" type="text" class="mt-1 block w-full" maxlength="30" autocomplete="tel" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>

            <div>
                <x-input-label for="date_of_birth" value="Tanggal lahir" />
                <x-text-input wire:model="date_of_birth" id="date_of_birth" type="date" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('date_of_birth')" />
            </div>

            <div>
                <x-input-label for="gender" value="Gender" />
                <select wire:model="gender" id="gender" class="form-input mt-1 block w-full">
                    <option value="">Tidak diisi</option>
                    <option value="female">Perempuan</option>
                    <option value="male">Laki-laki</option>
                    <option value="other">Lainnya</option>
                    <option value="prefer_not_to_say">Pilih untuk tidak menyebutkan</option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('gender')" />
            </div>

            <div>
                <x-input-label for="theme_preference" value="Mode tampilan" />
                <select wire:model="theme_preference" id="theme_preference" class="form-input mt-1 block w-full">
                    <option value="system">Ikuti perangkat</option>
                    <option value="light">Terang</option>
                    <option value="dark">Gelap</option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('theme_preference')" />
            </div>

            <div>
                <x-input-label for="timezone" value="Zona waktu" />
                <x-text-input wire:model="timezone" id="timezone" type="text" class="mt-1 block w-full" maxlength="64" placeholder="Asia/Jakarta" />
                <x-input-error class="mt-2" :messages="$errors->get('timezone')" />
            </div>

            <div>
                <x-input-label for="locale" value="Bahasa" />
                <select wire:model="locale" id="locale" class="form-input mt-1 block w-full">
                    <option value="id">Indonesia</option>
                    <option value="en">English</option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('locale')" />
            </div>
        </div>

        <div>
            <x-input-label for="bio" value="Bio singkat" />
            <textarea wire:model="bio" id="bio" rows="4" maxlength="500" class="form-input mt-1 block w-full resize-y" placeholder="Catatan pendek tentang kamu"></textarea>
            <div class="mt-2 flex items-center justify-between gap-3">
                <x-input-error :messages="$errors->get('bio')" />
                <p class="text-xs text-gray-400 dark:text-slate-500">Maksimal 500 karakter.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                Simpan profil
            </button>

            <x-action-message class="text-sm font-medium text-emerald-600 dark:text-emerald-300" on="profile-details-updated">
                Tersimpan.
            </x-action-message>
        </div>
    </form>
</section>
