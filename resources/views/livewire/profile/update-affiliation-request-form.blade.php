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

        <form wire:submit="submitAffiliationRequest" class="mt-5 space-y-4">
            @if ($hasVerifiedAffiliation)
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-100">
                    Isi data afiliasi baru. Afiliasi lama tetap aktif sampai perubahan ini disetujui.
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="affiliation_type" class="form-label">Jenis Afiliasi</label>
                    <select id="affiliation_type" wire:model="affiliation_type" class="mt-1 form-input">
                        @foreach ($this->affiliationTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
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
                <input id="affiliation_name" wire:model="affiliation_name" type="text" list="affiliation-template-options" class="mt-1 form-input" placeholder="Contoh: Universitas Indonesia">
                <datalist id="affiliation-template-options">
                    @foreach ($templates as $template)
                        <option value="{{ $template->affiliation_name }}"></option>
                    @endforeach
                </datalist>
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
