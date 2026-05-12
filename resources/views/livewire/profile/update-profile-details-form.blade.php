<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    public array $off_days = [];
    public bool $auto_national_holidays = true;
    public ?string $avatarUrl = null;
    public bool $remove_avatar = false;
    public string $context = 'workspace';
    public $avatar = null;

    public function mount(string $context = 'workspace'): void
    {
        $this->context = $context === 'admin' ? 'admin' : 'workspace';
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
        $this->off_days = array_map('intval', $profile?->preferences['off_days'] ?? [0, 6]);
        $this->auto_national_holidays = (bool) ($profile?->preferences['auto_national_holidays'] ?? true);
        $this->avatarUrl = $profile?->avatar_url;
    }

    public function updatedAvatar(): void
    {
        $this->remove_avatar = false;

        $this->validateOnly('avatar', [
            'avatar' => ['nullable', 'file', 'mimetypes:image/webp', 'max:512'],
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
            'avatar' => ['nullable', 'file', 'mimetypes:image/webp', 'max:512'],
            'off_days' => ['nullable', 'array'],
            'off_days.*' => ['integer', 'min:0', 'max:6'],
            'auto_national_holidays' => ['required', 'boolean'],
        ]);

        $user = Auth::user();
        $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);

        if ($this->avatar) {
            $path = $this->avatar->getRealPath();
            $binary = $path ? file_get_contents($path) : false;

            if ($binary === false || $binary === '') {
                $this->addError('avatar', 'Foto profil tidak bisa dibaca.');
                return;
            }

            [$width, $height] = getimagesizefromstring($binary) ?: [0, 0];
            if ($width < 64 || $height < 64 || $width > 256 || $height > 256) {
                $this->addError('avatar', 'Foto profil harus sudah dikompres ke ukuran 64 sampai 256 px.');
                return;
            }

            $user->profileAvatar()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'image' => $binary,
                    'mime_type' => 'image/webp',
                    'width' => $width,
                    'height' => $height,
                    'size' => strlen($binary),
                ]
            );

            if ($profile->avatar_path) {
                Storage::disk('public')->delete($profile->avatar_path);
                $profile->avatar_path = null;
            }

            $this->remove_avatar = false;
        } elseif ($this->remove_avatar) {
            $user->profileAvatar()->delete();

            if ($profile->avatar_path) {
                Storage::disk('public')->delete($profile->avatar_path);
                $profile->avatar_path = null;
            }
        }

        $preferences = $profile->preferences ?? [];
        if ($this->context !== 'admin') {
            $preferences = array_merge($preferences, [
                'off_days' => array_map('intval', $this->off_days),
                'auto_national_holidays' => $this->auto_national_holidays,
            ]);
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
            'preferences' => $preferences,
        ]);

        $profile->save();

        $this->reset('avatar');
        $this->remove_avatar = false;
        $this->avatarUrl = $user->profileAvatar()->exists()
            ? route('profile.avatar.show', ['user' => $user->id, 'v' => Str::uuid()->toString()], false)
            : $profile->avatar_url;

        $displayName = trim((string) ($profile->display_name ?: $user->name));
        $displayName = $displayName !== '' ? $displayName : 'Pengguna';

        $this->dispatch(
            'profile-details-updated',
            avatarUrl: $this->avatarUrl,
            displayName: $displayName,
            initial: mb_strtoupper(mb_substr($displayName, 0, 1)),
        );
        $this->dispatch('profile-theme-updated', theme: $profile->theme_preference);
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();
        $profile = $user->profile;

        $this->reset('avatar');
        $this->remove_avatar = true;
        $this->avatarUrl = null;

        $displayName = trim((string) ($profile?->display_name ?: $user->name));
        $displayName = $displayName !== '' ? $displayName : 'Pengguna';

        $this->dispatch(
            'profile-details-updated',
            avatarUrl: null,
            displayName: $displayName,
            initial: mb_strtoupper(mb_substr($displayName, 0, 1)),
        );
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}; ?>

<section>
    <header class="space-y-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">
            {{ $context === 'admin' ? 'Profil admin' : 'Profil opsional' }}
        </p>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">
            {{ $context === 'admin' ? 'Tampilan dan identitas admin' : 'Tampilan dan identitas workspace' }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-slate-400">
            {{ $context === 'admin'
                ? 'Data ini hanya mengatur tampilan profil admin dan preferensi panel.'
                : 'Data ini tidak mempengaruhi login. Gunakan untuk foto profil, nama tampilan, dan preferensi antarmuka.' }}
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
                    <p class="text-xs text-gray-500 dark:text-slate-400">PNG, JPG, atau WebP. Dipotong dan dikompres sebelum diunggah.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <label for="profile_avatar"
                           class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        Pilih foto
                    </label>
                    <input id="profile_avatar" type="file" wire:model="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" data-profile-avatar-input>
                    @if ($avatarUrl)
                        <button type="button"
                                wire:click="removeAvatar"
                                class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-200 dark:hover:bg-rose-500/10">
                            Hapus foto
                        </button>
                    @endif
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
                @if ($remove_avatar)
                    <p class="text-xs font-medium text-amber-600 dark:text-amber-300">
                        Foto akan dihapus setelah profil disimpan.
                    </p>
                @endif
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

        @if ($context !== 'admin')
            <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 dark:border-slate-800 dark:bg-slate-800/30">
                <x-input-label value="Hari Libur Rutin" />
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Pilih hari-hari di mana jadwal perkuliahan Anda biasanya libur.</p>
                
                <div class="mt-4 flex flex-wrap gap-x-8 gap-y-4">
                    @foreach([
                        1 => 'Senin',
                        2 => 'Selasa',
                        3 => 'Rabu',
                        4 => 'Kamis',
                        5 => 'Jumat',
                        6 => 'Sabtu',
                        0 => 'Minggu'
                    ] as $value => $label)
                        <label class="inline-flex cursor-pointer items-center group">
                            <input type="checkbox" 
                                   wire:model="off_days" 
                                   value="{{ $value }}"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:focus:ring-offset-slate-900 transition-all group-hover:border-indigo-400">
                            <span class="ml-2 text-xs font-medium text-gray-600 dark:text-slate-300 group-hover:text-indigo-600 transition-colors">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('off_days')" />
            </div>

            <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/10">
                <label class="flex cursor-pointer items-center justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">Hari Libur Nasional Otomatis</p>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Otomatis tandai tanggal merah resmi pemerintah sebagai hari libur di kalender.</p>
                    </div>
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" 
                               wire:model="auto_national_holidays" 
                               id="auto_national_holidays"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:focus:ring-offset-slate-900">
                    </div>
                </label>
            </div>
        @endif

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
                {{ $context === 'admin' ? 'Simpan profil admin' : 'Simpan profil' }}
            </button>

            <x-action-message class="text-sm font-medium text-emerald-600 dark:text-emerald-300" on="profile-details-updated">
                Tersimpan.
            </x-action-message>
        </div>
    </form>

    <div wire:ignore
         data-profile-avatar-cropper
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/70 px-4 py-6">
        <div class="w-full max-w-md rounded-2xl border border-white/10 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Edit foto profil</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Geser dan perbesar foto sebelum disimpan.</p>
                </div>
                <button type="button"
                        data-avatar-crop-cancel
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-gray-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                        aria-label="Tutup editor foto">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="mt-5 flex justify-center">
                <div data-avatar-crop-stage
                     class="relative h-72 w-72 max-w-full touch-none overflow-hidden rounded-3xl bg-slate-100 shadow-inner dark:bg-slate-800">
                    <img data-avatar-crop-image alt="" class="absolute max-w-none select-none" draggable="false">
                    <div class="pointer-events-none absolute inset-0 ring-2 ring-inset ring-white/90 dark:ring-slate-100/80"></div>
                </div>
            </div>

            <div class="mt-5 space-y-2">
                <label for="profile_avatar_zoom" class="text-sm font-medium text-gray-700 dark:text-slate-200">Zoom</label>
                <input id="profile_avatar_zoom"
                       data-avatar-crop-zoom
                       type="range"
                       min="1"
                       max="6"
                       step="0.01"
                       value="1"
                       class="w-full accent-indigo-600">
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <button type="button"
                        data-avatar-crop-cancel
                        class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    Batal
                </button>
                <button type="button"
                        data-avatar-crop-apply
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Gunakan foto
                </button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const AVATAR_OUTPUT_SIZE = 256;
            const AVATAR_OUTPUT_QUALITY = 0.75;
            const AVATAR_MAX_ZOOM = 6;

            const bindProfileDetailsUpdates = () => {
                if (window.__profileDetailsDomBound === true) {
                    return;
                }

                window.__profileDetailsDomBound = true;

                window.addEventListener('profile-details-updated', (event) => {
                    const detail = event.detail || {};
                    const avatarUrl = detail.avatarUrl || null;
                    const initial = detail.initial || 'P';
                    const displayName = detail.displayName || '';

                    document.querySelectorAll('[data-profile-avatar-frame]').forEach((frame) => {
                        frame.dataset.profileAvatarInitial = initial;
                        frame.innerHTML = '';

                        if (avatarUrl) {
                            const image = document.createElement('img');
                            image.src = avatarUrl;
                            image.alt = frame.dataset.profileAvatarAlt || '';
                            image.className = 'h-full w-full object-cover';
                            frame.appendChild(image);
                            return;
                        }

                        frame.textContent = initial;
                    });

                    if (displayName !== '') {
                        document.querySelectorAll('[data-profile-display-name]').forEach((element) => {
                            element.textContent = displayName;
                        });
                    }
                });
            };

            const initProfileAvatarOptimizer = () => {
                document.querySelectorAll('[data-profile-avatar-input]').forEach((input) => {
                    if (input.dataset.optimizerBound === 'true') {
                        return;
                    }

                    input.dataset.optimizerBound = 'true';

                    input.addEventListener('change', async (event) => {
                        if (input.dataset.optimized === 'true') {
                            delete input.dataset.optimized;
                            return;
                        }

                        const file = input.files && input.files[0];
                        if (! file) {
                            return;
                        }

                        event.preventDefault();
                        event.stopImmediatePropagation();

                        try {
                            await openProfileAvatarCropper(file, input);
                        } catch (error) {
                            input.value = '';
                            alert(error.message || 'Foto profil tidak bisa dikompres di browser ini.');
                        }
                    }, true);
                });
            };

            const openProfileAvatarCropper = async (file, input) => {
                if (! /^image\/(jpeg|png|webp)$/.test(file.type)) {
                    throw new Error('Format foto harus PNG, JPG, atau WebP.');
                }

                const modal = document.querySelector('[data-profile-avatar-cropper]');
                const stage = modal?.querySelector('[data-avatar-crop-stage]');
                const imageElement = modal?.querySelector('[data-avatar-crop-image]');
                const zoomInput = modal?.querySelector('[data-avatar-crop-zoom]');
                const applyButton = modal?.querySelector('[data-avatar-crop-apply]');
                const cancelButtons = modal ? modal.querySelectorAll('[data-avatar-crop-cancel]') : [];

                if (! modal || ! stage || ! imageElement || ! zoomInput || ! applyButton) {
                    throw new Error('Editor foto profil tidak tersedia.');
                }

                const image = await loadImage(file);
                const sourceUrl = image.src;
                const state = {
                    file,
                    input,
                    image,
                    imageElement,
                    stage,
                    zoomInput,
                    zoom: 1,
                    offsetX: 0,
                    offsetY: 0,
                    dragging: false,
                    lastX: 0,
                    lastY: 0,
                };

                imageElement.src = sourceUrl;
                zoomInput.value = '1';
                modal.__avatarCropState = state;

                const close = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    imageElement.removeAttribute('src');
                    delete modal.__avatarCropState;
                    URL.revokeObjectURL(sourceUrl);
                };

                const cancel = () => {
                    input.value = '';
                    close();
                };

                const apply = async () => {
                    try {
                        const optimized = await renderCroppedAvatar(state);
                        const transfer = new DataTransfer();
                        transfer.items.add(optimized);
                        input.files = transfer.files;
                        input.dataset.optimized = 'true';
                        close();
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    } catch (error) {
                        alert(error.message || 'Foto profil tidak bisa dikompres di browser ini.');
                    }
                };

                modal.__avatarCropCancel = cancel;
                modal.__avatarCropApply = apply;

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                requestAnimationFrame(() => applyCropState(state));
            };

            const bindProfileAvatarCropper = () => {
                const modal = document.querySelector('[data-profile-avatar-cropper]');
                if (! modal || modal.dataset.cropperBound === 'true') {
                    return;
                }

                modal.dataset.cropperBound = 'true';

                const stage = modal.querySelector('[data-avatar-crop-stage]');
                const zoomInput = modal.querySelector('[data-avatar-crop-zoom]');
                const applyButton = modal.querySelector('[data-avatar-crop-apply]');
                const cancelButtons = modal.querySelectorAll('[data-avatar-crop-cancel]');

                zoomInput.addEventListener('input', () => {
                    const state = modal.__avatarCropState;
                    if (! state) {
                        return;
                    }

                    state.zoom = Number.parseFloat(zoomInput.value) || 1;
                    state.zoom = Math.min(AVATAR_MAX_ZOOM, Math.max(1, state.zoom));
                    applyCropState(state);
                });

                stage.addEventListener('pointerdown', (event) => {
                    const state = modal.__avatarCropState;
                    if (! state) {
                        return;
                    }

                    state.dragging = true;
                    state.lastX = event.clientX;
                    state.lastY = event.clientY;
                    stage.setPointerCapture(event.pointerId);
                });

                stage.addEventListener('pointermove', (event) => {
                    const state = modal.__avatarCropState;
                    if (! state || ! state.dragging) {
                        return;
                    }

                    state.offsetX += event.clientX - state.lastX;
                    state.offsetY += event.clientY - state.lastY;
                    state.lastX = event.clientX;
                    state.lastY = event.clientY;
                    applyCropState(state);
                });

                stage.addEventListener('pointerup', (event) => {
                    const state = modal.__avatarCropState;
                    if (! state) {
                        return;
                    }

                    state.dragging = false;
                    stage.releasePointerCapture(event.pointerId);
                });

                stage.addEventListener('pointercancel', () => {
                    const state = modal.__avatarCropState;
                    if (state) {
                        state.dragging = false;
                    }
                });

                applyButton.addEventListener('click', () => {
                    modal.__avatarCropApply?.();
                });

                cancelButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        modal.__avatarCropCancel?.();
                    });
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && ! modal.classList.contains('hidden')) {
                        modal.__avatarCropCancel?.();
                    }
                });
            };

            const applyCropState = (state) => {
                const rect = state.stage.getBoundingClientRect();
                const baseScale = Math.max(rect.width / state.image.naturalWidth, rect.height / state.image.naturalHeight);
                const width = state.image.naturalWidth * baseScale * state.zoom;
                const height = state.image.naturalHeight * baseScale * state.zoom;
                const minX = Math.min(0, rect.width - width);
                const minY = Math.min(0, rect.height - height);

                state.offsetX = Math.max(minX / 2, Math.min(-minX / 2, state.offsetX));
                state.offsetY = Math.max(minY / 2, Math.min(-minY / 2, state.offsetY));

                state.render = {
                    width,
                    height,
                    left: (rect.width - width) / 2 + state.offsetX,
                    top: (rect.height - height) / 2 + state.offsetY,
                    stageWidth: rect.width,
                    stageHeight: rect.height,
                };

                state.imageElement.style.width = `${width}px`;
                state.imageElement.style.height = `${height}px`;
                state.imageElement.style.maxWidth = 'none';
                state.imageElement.style.maxHeight = 'none';
                state.imageElement.style.left = `${state.render.left}px`;
                state.imageElement.style.top = `${state.render.top}px`;
            };

            const renderCroppedAvatar = async (state) => {
                applyCropState(state);

                const render = state.render;
                const canvas = document.createElement('canvas');
                canvas.width = AVATAR_OUTPUT_SIZE;
                canvas.height = AVATAR_OUTPUT_SIZE;

                const context = canvas.getContext('2d');
                const sourceX = Math.max(0, -render.left / render.width * state.image.naturalWidth);
                const sourceY = Math.max(0, -render.top / render.height * state.image.naturalHeight);
                const sourceWidth = render.stageWidth / render.width * state.image.naturalWidth;
                const sourceHeight = render.stageHeight / render.height * state.image.naturalHeight;

                context.drawImage(
                    state.image,
                    sourceX,
                    sourceY,
                    sourceWidth,
                    sourceHeight,
                    0,
                    0,
                    AVATAR_OUTPUT_SIZE,
                    AVATAR_OUTPUT_SIZE
                );

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', AVATAR_OUTPUT_QUALITY));
                if (! blob) {
                    throw new Error('Browser ini belum mendukung kompresi WebP.');
                }

                const name = state.file.name.replace(/\.[^.]+$/, '') || 'avatar';
                return new File([blob], `${name}.webp`, {
                    type: 'image/webp',
                    lastModified: Date.now(),
                });
            };

            const loadImage = (file) => new Promise((resolve, reject) => {
                const url = URL.createObjectURL(file);
                const image = new Image();

                image.onload = () => {
                    resolve(image);
                };

                image.onerror = () => {
                    URL.revokeObjectURL(url);
                    reject(new Error('Foto profil tidak bisa dibaca.'));
                };

                image.src = url;
            });

            const init = () => {
                bindProfileDetailsUpdates();
                bindProfileAvatarCropper();
                initProfileAvatarOptimizer();
            };

            document.addEventListener('DOMContentLoaded', init);
            document.addEventListener('livewire:navigated', init);
            init();
        })();
    </script>
</section>
