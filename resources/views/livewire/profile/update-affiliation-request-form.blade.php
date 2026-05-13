<?php

use App\Models\AffiliationRequest;
use App\Models\AffiliationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $affiliation_type = 'university';
    public string $affiliation_name = '';
    public string $student_id_type = 'nim';
    public string $student_id_number = '';
    public bool $showChangeForm = false;

    public function mount(): void
    {
        $user = Auth::user();

        $this->affiliation_type = (string) ($user->affiliation_type ?: 'university');
        $this->affiliation_name = (string) ($user->affiliation_name ?? '');
        $this->student_id_type = (string) ($user->student_id_type ?: 'nim');
        $this->student_id_number = (string) ($user->student_id_number ?? '');
    }

    public function submitAffiliationRequest(): void
    {
        $user = Auth::user();

        if ($user->pendingAffiliationRequest()->exists()) {
            $this->addError('affiliation_name', 'Masih ada pengajuan afiliasi yang menunggu review.');
            return;
        }

        $validated = $this->validate([
            'affiliation_type' => ['required', Rule::in(array_keys($this->affiliationTypeOptions()))],
            'affiliation_name' => ['required', 'string', 'max:160'],
            'student_id_type' => ['required', Rule::in(array_keys($this->identityTypeOptions()))],
            'student_id_number' => ['required', 'string', 'max:64'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->pendingAffiliationRequest()->exists()) {
                throw ValidationException::withMessages([
                    'affiliation_name' => 'Masih ada pengajuan afiliasi yang menunggu review.',
                ]);
            }

            AffiliationRequest::query()->create([
                'user_id' => $lockedUser->id,
                'affiliation_type' => $validated['affiliation_type'],
                'affiliation_name' => $this->normalize($validated['affiliation_name']),
                'student_id_type' => $this->nullableString($validated['student_id_type'] ?? null),
                'student_id_number' => $this->normalize($validated['student_id_number']),
                'status' => AffiliationRequest::STATUS_PENDING,
            ]);
        });

        $this->showChangeForm = false;
        $this->dispatch('affiliation-request-updated');
    }

    public function showAffiliationChangeForm(): void
    {
        $this->resetErrorBag();

        $user = Auth::user();
        $this->affiliation_type = (string) ($user->affiliation_type ?: 'university');
        $this->affiliation_name = (string) ($user->affiliation_name ?? '');
        $this->student_id_type = (string) ($user->student_id_type ?: 'nim');
        $this->student_id_number = (string) ($user->student_id_number ?? '');
        $this->showChangeForm = true;
    }

    public function hideAffiliationChangeForm(): void
    {
        $this->resetErrorBag();

        $user = Auth::user();
        $this->affiliation_type = (string) ($user->affiliation_type ?: 'university');
        $this->affiliation_name = (string) ($user->affiliation_name ?? '');
        $this->student_id_type = (string) ($user->student_id_type ?: 'nim');
        $this->student_id_number = (string) ($user->student_id_number ?? '');
        $this->showChangeForm = false;
    }

    public function cancelPendingRequest(): void
    {
        $request = Auth::user()->pendingAffiliationRequest;

        if (! $request) {
            return;
        }

        $request->forceFill([
            'status' => AffiliationRequest::STATUS_CANCELED,
            'reviewed_at' => now(),
        ])->save();

        $this->dispatch('affiliation-request-updated');
    }

    public function with(): array
    {
        $user = Auth::user()->load('pendingAffiliationRequest');

        return [
            'user' => $user,
            'pendingRequest' => $user->pendingAffiliationRequest,
            'latestRejectedRequest' => $user->affiliationRequests()
                ->where('status', AffiliationRequest::STATUS_REJECTED)
                ->latest()
                ->first(),
            'templates' => AffiliationTemplate::query()
                ->where('is_active', true)
                ->orderBy('affiliation_name')
                ->limit(50)
                ->get(['affiliation_name']),
            'templateSuggestions' => AffiliationTemplate::query()
                ->where('is_active', true)
                ->orderBy('affiliation_name')
                ->limit(200)
                ->get(['affiliation_type', 'affiliation_name', 'aliases'])
                ->map(fn (AffiliationTemplate $template): array => [
                    'type' => $template->affiliation_type,
                    'name' => $template->affiliation_name,
                    'aliases' => array_values(array_filter((array) ($template->aliases ?? []))),
                ])
                ->unique(fn (array $template): string => mb_strtolower(preg_replace('/\s+/', ' ', trim($template['name']))))
                ->values(),
        ];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: trim($value);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function hasVerifiedAffiliation(User $user): bool
    {
        return $user->affiliation_status === 'verified'
            && (filled($user->affiliation_template_id) || filled($user->affiliation_name));
    }

    /**
     * @return array<string, string>
     */
    public function affiliationTypeOptions(): array
    {
        return [
            'school' => 'Sekolah',
            'university' => 'Universitas',
            'institute' => 'Institut',
            'polytechnic' => 'Politeknik',
            'academy' => 'Akademi',
            'organization' => 'Organisasi',
            'company' => 'Perusahaan',
            'foundation' => 'Yayasan',
            'other' => 'Lainnya',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function identityTypeOptions(): array
    {
        return [
            'nim' => 'NIM',
            'nrp' => 'NRP',
            'nisn' => 'NISN',
            'nidn' => 'NIDN',
            'nip' => 'NIP',
            'other' => 'Lainnya',
        ];
    }

    public function identityTypeLabel(?string $value): string
    {
        $value = trim((string) $value);

        return $this->identityTypeOptions()[$value] ?? strtoupper($value ?: 'ID');
    }
}; ?>

<section>
    @php
        $hasVerifiedAffiliation = $this->hasVerifiedAffiliation($user);
        $statusLabel = match (true) {
            $hasVerifiedAffiliation => 'Terverifikasi',
            $user->affiliation_status === 'rejected' => 'Ditolak',
            default => 'Menunggu review',
        };
        $statusClass = match (true) {
            $hasVerifiedAffiliation => 'text-emerald-600 dark:text-emerald-300',
            $user->affiliation_status === 'rejected' => 'text-rose-600 dark:text-rose-300',
            default => 'text-amber-600 dark:text-amber-300',
        };
        $shouldShowAffiliationForm = ! $hasVerifiedAffiliation || $showChangeForm;
    @endphp

    <header class="space-y-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Afiliasi</p>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Kampus dan identitas</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Ajukan perubahan afiliasi untuk diverifikasi super admin. Data aktif berubah setelah disetujui.
        </p>
    </header>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
            <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Afiliasi Aktif</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $user->affiliation_name ?: 'Belum diisi' }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $this->identityTypeLabel($user->student_id_type) }}: {{ $user->student_id_number ?: '-' }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
            <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Status</p>
            <p class="mt-1 text-sm font-semibold {{ $statusClass }}">
                {{ $statusLabel }}
            </p>
            @if ($hasVerifiedAffiliation && $user->affiliation_verified_at)
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Diverifikasi {{ $user->affiliation_verified_at->diffForHumans() }}</p>
            @endif
        </div>
    </div>

    @if ($pendingRequest)
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">Pengajuan sedang menunggu review</p>
                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                        {{ $pendingRequest->affiliation_name }} - {{ $this->identityTypeLabel($pendingRequest->student_id_type) }} {{ $pendingRequest->student_id_number }}
                    </p>
                </div>
                <button type="button"
                        wire:click="cancelPendingRequest"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100 disabled:opacity-60 dark:border-amber-400/40 dark:text-amber-100 dark:hover:bg-amber-500/20">
                    Batalkan
                </button>
            </div>
        </div>
    @else
        @if ($hasVerifiedAffiliation)
        <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">Afiliasi sudah terverifikasi</p>
                    <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
                        Data aktif tetap digunakan sampai pengajuan perubahan disetujui super admin.
                    </p>
                </div>
                @unless ($showChangeForm)
                    <button type="button"
                            wire:click="showAffiliationChangeForm"
                            class="inline-flex items-center justify-center rounded-xl border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100 dark:border-emerald-400/40 dark:text-emerald-100 dark:hover:bg-emerald-500/20">
                        Ajukan pindah afiliasi
                    </button>
                @endunless
            </div>
        </div>
        @endif

        @if ($shouldShowAffiliationForm)
        @if ($latestRejectedRequest)
            <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                <p class="text-sm font-semibold text-rose-900 dark:text-rose-100">Pengajuan terakhir ditolak</p>
                <p class="mt-1 text-sm text-rose-800 dark:text-rose-200">
                    {{ $latestRejectedRequest->rejection_reason ?: 'Silakan periksa kembali data afiliasi dan nomor identitas.' }}
                </p>
            </div>
        @endif

        <form wire:submit="submitAffiliationRequest"
              class="mt-5 space-y-4"
              data-affiliation-smart-type-form>
            @if ($hasVerifiedAffiliation)
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-100">
                    Isi data afiliasi baru. Afiliasi lama tetap aktif sampai perubahan ini disetujui.
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="affiliation_type" class="form-label">Jenis Afiliasi</label>
                    <select id="affiliation_type"
                            wire:model.live="affiliation_type"
                            class="mt-1 form-input"
                            data-affiliation-type-select>
                        @foreach ($this->affiliationTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 hidden text-xs text-indigo-600 dark:text-indigo-300" data-affiliation-type-hint></p>
                    @error('affiliation_type') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="student_id_type" class="form-label">Tipe Identitas</label>
                    <select id="student_id_type" wire:model="student_id_type" class="mt-1 form-input">
                        @foreach ($this->identityTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('student_id_type') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="affiliation_name" class="form-label">Nama Afiliasi</label>
                <div class="relative mt-1" data-affiliation-combobox>
                    <input id="affiliation_name"
                           wire:model.live.debounce.150ms="affiliation_name"
                           type="text"
                           autocomplete="off"
                           spellcheck="false"
                           aria-autocomplete="list"
                           aria-expanded="false"
                           class="form-input"
                           placeholder="Contoh: Universitas Indonesia"
                           data-affiliation-name-input>
                    <div class="absolute left-0 right-0 top-full z-50 mt-2 hidden max-h-56 overflow-y-auto rounded-2xl border border-gray-200 bg-white p-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-950"
                         data-affiliation-suggestions
                         wire:ignore></div>
                </div>
                @error('affiliation_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="student_id_number" class="form-label">Nomor Identitas</label>
                <input id="student_id_number" wire:model="student_id_number" type="text" class="mt-1 form-input" placeholder="Masukkan nomor identitas">
                @error('student_id_number') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <x-action-message class="text-sm font-medium text-emerald-600 dark:text-emerald-300" on="affiliation-request-updated">
                    Pengajuan diperbarui.
                </x-action-message>
                @if ($hasVerifiedAffiliation)
                    <button type="button"
                            wire:click="hideAffiliationChangeForm"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 disabled:opacity-60 dark:border-slate-700 dark:text-slate-300">
                        Batal
                    </button>
                @endif
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Submit Pengajuan
                </button>
            </div>
        </form>
        @endif
    @endif
</section>

@script
<script>
    const affiliationTemplateSuggestions = @js($templateSuggestions);

    const normalizeAffiliationName = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const affiliationTypeLabels = {
        school: 'Sekolah',
        university: 'Universitas',
        institute: 'Institut',
        polytechnic: 'Politeknik',
        academy: 'Akademi',
        organization: 'Organisasi',
        company: 'Perusahaan',
        foundation: 'Yayasan',
        other: 'Lainnya',
    };

    const preparedAffiliationTemplates = (affiliationTemplateSuggestions || [])
        .map((template) => {
            const name = String(template.name || '').trim();
            const aliases = Array.isArray(template.aliases) ? template.aliases : [];
            const phrases = [name, ...aliases]
                .map((phrase) => normalizeAffiliationName(String(phrase || '')))
                .filter(Boolean);

            return {
                type: template.type || null,
                name,
                normalizedName: normalizeAffiliationName(name),
                phrases,
            };
        })
        .filter((template) => template.name && template.normalizedName);

    const scoreAffiliationTemplate = (template, value) => {
        const input = normalizeAffiliationName(value);

        if (!input) {
            return 0;
        }

        if (template.normalizedName.startsWith(input)) {
            return 1000 + input.length;
        }

        const phraseMatch = template.phrases.some((phrase) => phrase.startsWith(input));

        if (phraseMatch) {
            return 850 + input.length;
        }

        const inputTokens = input.split(' ');
        const templateTokens = template.normalizedName.split(' ');
        let score = 0;

        for (let index = 0; index < inputTokens.length; index += 1) {
            const inputToken = inputTokens[index] || '';
            const templateToken = templateTokens[index] || '';

            if (!inputToken || !templateToken.startsWith(inputToken)) {
                return 0;
            }

            score += inputToken.length;
        }

        return 500 + score;
    };

    const findAffiliationMatches = (value, limit = 4) => preparedAffiliationTemplates
        .map((template) => ({
            ...template,
            score: scoreAffiliationTemplate(template, value),
        }))
        .filter((template) => template.score > 0)
        .sort((first, second) => second.score - first.score || first.name.localeCompare(second.name))
        .slice(0, limit);

    const templateTypeSuggestion = (template) => {
        if (!template?.type || !affiliationTypeLabels[template.type]) {
            return null;
        }

        return { value: template.type, label: affiliationTypeLabels[template.type] };
    };

    const fallbackAffiliationCompletions = [
        { type: 'institute', name: 'Institut' },
        { type: 'polytechnic', name: 'Politeknik' },
        { type: 'university', name: 'Universitas' },
        { type: 'school', name: 'Sekolah' },
        { type: 'academy', name: 'Akademi' },
        { type: 'organization', name: 'Organisasi' },
        { type: 'company', name: 'Perusahaan' },
        { type: 'foundation', name: 'Yayasan' },
    ].map((template) => ({
        ...template,
        normalizedName: normalizeAffiliationName(template.name),
        phrases: [normalizeAffiliationName(template.name)],
    }));

    const findFallbackAffiliationCompletion = (value) => fallbackAffiliationCompletions
        .map((template) => ({
            ...template,
            score: scoreAffiliationTemplate(template, value),
        }))
        .filter((template) => template.score > 0)
        .sort((first, second) => second.score - first.score)
        .at(0) || null;

    const inferAffiliationType = (value) => {
        const text = normalizeAffiliationName(value);
        const padded = ` ${text} `;

        if (!text) {
            return null;
        }

        const hasAny = (patterns) => patterns.some((pattern) => pattern.test(padded));

        if (hasAny([/\bpoliteknik\b/, /\bpoltek\b/, /\bpolytechnic\b/])) {
            return { value: 'polytechnic', label: 'Politeknik' };
        }

        if (hasAny([/\binstitut\b/, /\binstitute\b/, /\bsek institut\b/, /\bitb\b/, /\bits\b/])) {
            return { value: 'institute', label: 'Institut' };
        }

        if (hasAny([/\bakademi\b/, /\bacademy\b/, /\bakper\b/, /\bakbid\b/])) {
            return { value: 'academy', label: 'Akademi' };
        }

        if (hasAny([/\buniversitas\b/, /\buniversity\b/, /\buniv\b/, /\buin\b/, /\bugm\b/, /\bunesa\b/, /\bundip\b/, /\bunair\b/, /\bunhas\b/, /\bunpad\b/])) {
            return { value: 'university', label: 'Universitas' };
        }

        if (hasAny([/\bsmk\b/, /\bsma\b/, /\bma\b/, /\bmts\b/, /\bsmp\b/, /\bsd\b/, /\bsekolah\b/, /\bmadrasah\b/, /\bpesantren\b/])) {
            return { value: 'school', label: 'Sekolah' };
        }

        if (hasAny([/\byayasan\b/, /\bfoundation\b/])) {
            return { value: 'foundation', label: 'Yayasan' };
        }

        if (hasAny([/\bpt\b/, /\bcv\b/, /\bcompany\b/, /\bperusahaan\b/, /\bcorp\b/, /\bcorporation\b/, /\binc\b/, /\bltd\b/])) {
            return { value: 'company', label: 'Perusahaan' };
        }

        if (hasAny([/\borganisasi\b/, /\borganization\b/, /\bkomunitas\b/, /\bcommunity\b/, /\bhimpunan\b/, /\bukm\b/, /\bormawa\b/])) {
            return { value: 'organization', label: 'Organisasi' };
        }

        return null;
    };

    const dispatchLivewireInput = (element) => {
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const setupAffiliationSmartType = () => {
        document.querySelectorAll('[data-affiliation-smart-type-form]').forEach((form) => {
            const nameInput = form.querySelector('[data-affiliation-name-input]');
            const typeSelect = form.querySelector('[data-affiliation-type-select]');
            const hint = form.querySelector('[data-affiliation-type-hint]');
            const suggestionsPanel = form.querySelector('[data-affiliation-suggestions]');

            if (!nameInput || !typeSelect) {
                return;
            }

            if (
                nameInput.dataset.smartTypeReady === '1'
                && typeSelect.dataset.smartTypeReady === '1'
                && nameInput.dataset.smartAutocompleteReady === '1'
            ) {
                return;
            }

            nameInput.dataset.smartTypeReady = '1';
            typeSelect.dataset.smartTypeReady = '1';
            nameInput.dataset.smartAutocompleteReady = '1';

            let userChangedType = typeSelect.dataset.affiliationManualType === '1';
            let applyingSmartType = false;
            let activeSuggestionIndex = -1;

            const updateHint = (suggestion, applied) => {
                if (!hint) {
                    return;
                }

                if (!suggestion) {
                    hint.textContent = '';
                    hint.classList.add('hidden');
                    return;
                }

                hint.textContent = applied
                    ? `Jenis disarankan otomatis: ${suggestion.label}.`
                    : `Saran jenis: ${suggestion.label}. Pilihan manual kamu tetap dipakai.`;
                hint.classList.remove('hidden');
            };

            const setNameValue = (value) => {
                nameInput.value = value;
                dispatchLivewireInput(nameInput);
                nameInput.focus();
                const position = nameInput.value.length;
                nameInput.setSelectionRange(position, position);
            };

            const closeSuggestions = () => {
                activeSuggestionIndex = -1;

                if (suggestionsPanel) {
                    suggestionsPanel.innerHTML = '';
                    suggestionsPanel.classList.add('hidden');
                }

                nameInput.setAttribute('aria-expanded', 'false');
            };

            const applyTemplate = (template) => {
                setNameValue(template.name);

                if (template.type && typeSelect.querySelector(`option[value="${template.type}"]`)) {
                    applyingSmartType = true;
                    typeSelect.value = template.type;
                    dispatchLivewireInput(typeSelect);
                    applyingSmartType = false;
                    updateHint(templateTypeSuggestion(template), true);
                }

                closeSuggestions();
            };

            const markActiveSuggestion = (buttons) => {
                buttons.forEach((button, index) => {
                    const active = index === activeSuggestionIndex;
                    button.classList.toggle('bg-indigo-50', active);
                    button.classList.toggle('dark:bg-indigo-500/15', active);
                });
            };

            const renderSuggestions = () => {
                if (!suggestionsPanel) {
                    return [];
                }

                const matches = findAffiliationMatches(nameInput.value, 6);
                suggestionsPanel.innerHTML = '';

                if (matches.length === 0 || !nameInput.value.trim()) {
                    closeSuggestions();
                    return [];
                }

                matches.forEach((match) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left text-slate-700 hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none dark:text-slate-100 dark:hover:bg-indigo-500/15 dark:focus:bg-indigo-500/15';
                    button.innerHTML = '<span class="min-w-0 truncate font-semibold"></span><span class="shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-200"></span>';
                    button.querySelector('span:first-child').textContent = match.name;
                    button.querySelector('span:last-child').textContent = affiliationTypeLabels[match.type] || 'Afiliasi';
                    button.addEventListener('mousedown', (event) => event.preventDefault());
                    button.addEventListener('click', () => applyTemplate(match));
                    suggestionsPanel.appendChild(button);
                });

                activeSuggestionIndex = -1;
                suggestionsPanel.classList.remove('hidden');
                nameInput.setAttribute('aria-expanded', 'true');

                return matches;
            };

            const maybeApplySuggestion = () => {
                const match = findAffiliationMatches(nameInput.value, 1)[0] || null;
                const suggestion = templateTypeSuggestion(match) || inferAffiliationType(nameInput.value);

                if (!suggestion || !typeSelect.querySelector(`option[value="${suggestion.value}"]`)) {
                    updateHint(null, false);
                    return;
                }

                if (userChangedType) {
                    updateHint(suggestion, false);
                    return;
                }

                if (typeSelect.value !== suggestion.value) {
                    applyingSmartType = true;
                    typeSelect.value = suggestion.value;
                    dispatchLivewireInput(typeSelect);
                    applyingSmartType = false;
                }

                updateHint(suggestion, true);
            };

            typeSelect.addEventListener('change', () => {
                if (applyingSmartType) {
                    return;
                }

                userChangedType = true;
                typeSelect.dataset.affiliationManualType = '1';
                const suggestion = inferAffiliationType(nameInput.value);
                updateHint(suggestion, suggestion?.value === typeSelect.value);
            });

            nameInput.addEventListener('input', () => {
                maybeApplySuggestion();
                renderSuggestions();
            });
            nameInput.addEventListener('change', () => {
                maybeApplySuggestion();
                renderSuggestions();
            });
            nameInput.addEventListener('focus', renderSuggestions);
            nameInput.addEventListener('keydown', (event) => {
                if (!suggestionsPanel || suggestionsPanel.classList.contains('hidden')) {
                    return;
                }

                const buttons = Array.from(suggestionsPanel.querySelectorAll('button'));

                if (event.key === 'Escape') {
                    closeSuggestions();
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    activeSuggestionIndex = Math.min(activeSuggestionIndex + 1, buttons.length - 1);
                    markActiveSuggestion(buttons);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    activeSuggestionIndex = Math.max(activeSuggestionIndex - 1, 0);
                    markActiveSuggestion(buttons);
                    return;
                }

                if (event.key === 'Enter' && activeSuggestionIndex >= 0) {
                    event.preventDefault();
                    buttons[activeSuggestionIndex]?.click();
                }
            });
            nameInput.addEventListener('blur', () => {
                window.setTimeout(closeSuggestions, 120);
            });

            maybeApplySuggestion();
        });
    };

    setupAffiliationSmartType();

    document.addEventListener('livewire:navigated', setupAffiliationSmartType);
    document.addEventListener('livewire:initialized', () => {
        Livewire.hook('morph.updated', setupAffiliationSmartType);
    });
</script>
@endscript
