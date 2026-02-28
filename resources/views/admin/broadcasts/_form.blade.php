@php
    $broadcast = $broadcast ?? null;
    $targetOptions = $targetOptions ?? [];
    $canUseGlobal = $canUseGlobal ?? false;
    $selectedTargetValues = $selectedTargetValues ?? old('targets', []);
    $currentTargetMode = old(
        'target_mode',
        $broadcast?->target_mode ?? \App\Models\AffiliationBroadcast::TARGET_MODE_AFFILIATION
    );
@endphp

<div class="space-y-5">
    <div>
        <label for="title" class="form-label">Judul Broadcast</label>
        <input type="text"
               name="title"
               id="title"
               value="{{ old('title', $broadcast?->title) }}"
               class="mt-1 form-input"
               maxlength="180"
               required>
        @error('title')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="body" class="form-label">Isi Pesan</label>
        <textarea name="body"
                  id="body"
                  rows="8"
                  class="mt-1 form-input"
                  maxlength="10000"
                  required>{{ old('body', $broadcast?->body) }}</textarea>
        @error('body')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="image" class="form-label">Gambar (opsional)</label>
        <input type="file"
               name="image"
               id="image"
               accept="image/png,image/jpeg,image/webp,image/gif"
               class="mt-1 form-input">
        @error('image')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror

        @if ($broadcast?->image_path)
            <div class="mt-2 flex items-center gap-3">
                <img src="{{ $broadcast->image_url }}"
                     alt="Gambar broadcast"
                     class="h-16 w-16 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox"
                           name="remove_image"
                           value="1"
                           class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Hapus gambar saat simpan
                </label>
            </div>
        @endif
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        <div>
            <label class="form-label">Target Mode</label>
            <div class="mt-2 space-y-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="radio"
                           name="target_mode"
                           value="{{ \App\Models\AffiliationBroadcast::TARGET_MODE_AFFILIATION }}"
                           class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           @checked($currentTargetMode === \App\Models\AffiliationBroadcast::TARGET_MODE_AFFILIATION)>
                    Afiliasi Spesifik
                </label>
                @if ($canUseGlobal)
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="radio"
                               name="target_mode"
                               value="{{ \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL }}"
                               class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                               @checked($currentTargetMode === \App\Models\AffiliationBroadcast::TARGET_MODE_GLOBAL)>
                        Global (semua user)
                    </label>
                @else
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Admin hanya bisa mengirim ke afiliasi yang ditugaskan.
                    </p>
                @endif
            </div>
            @error('target_mode')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="form-label">Opsi Push</label>
            <div class="mt-2 space-y-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox"
                           name="send_push"
                           value="1"
                           class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           @checked(old('send_push', $broadcast?->send_push ?? true))>
                    Kirim push notification saat dipublish
                </label>
            </div>
            @error('send_push')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label class="form-label">Target Afiliasi</label>
        <div class="mt-2 max-h-56 overflow-auto rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            @if (count($targetOptions) === 0)
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada target afiliasi yang tersedia.</p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($targetOptions as $option)
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-2 py-1.5 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200">
                            <input type="checkbox"
                                   name="targets[]"
                                   value="{{ $option['value'] }}"
                                   class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                   @checked(in_array($option['value'], $selectedTargetValues, true))>
                            <span>{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
        @error('targets')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
        @error('targets.*')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>
