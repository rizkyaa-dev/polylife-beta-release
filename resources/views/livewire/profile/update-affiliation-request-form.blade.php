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

        if ($user->affiliation_status === 'verified') {
            $this->addError('affiliation_name', 'Afiliasi sudah terverifikasi. Pengajuan baru tidak tersedia.');
            return;
        }

        if ($user->pendingAffiliationRequest()->exists()) {
            $this->addError('affiliation_name', 'Masih ada pengajuan afiliasi yang menunggu review.');
            return;
        }

        $validated = $this->validate([
            'affiliation_type' => ['required', Rule::in(['university', 'school', 'organization', 'other'])],
            'affiliation_name' => ['required', 'string', 'max:160'],
            'student_id_type' => ['nullable', 'string', 'max:32'],
            'student_id_number' => ['required', 'string', 'max:64'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->affiliation_status === 'verified') {
                throw ValidationException::withMessages([
                    'affiliation_name' => 'Afiliasi sudah terverifikasi. Pengajuan baru tidak tersedia.',
                ]);
            }

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

        $this->dispatch('affiliation-request-updated');
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
}; ?>

<section>
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
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ strtoupper((string) ($user->student_id_type ?: 'ID')) }}: {{ $user->student_id_number ?: '-' }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/50">
            <p class="text-xs font-semibold uppercase text-gray-500 dark:text-slate-400">Status</p>
            <p class="mt-1 text-sm font-semibold {{ $user->affiliation_status === 'verified' ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' }}">
                {{ match ($user->affiliation_status) {
                    'verified' => 'Terverifikasi',
                    'rejected' => 'Ditolak',
                    default => 'Menunggu review',
                } }}
            </p>
            @if ($user->affiliation_verified_at)
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
                        {{ $pendingRequest->affiliation_name }} - {{ strtoupper((string) ($pendingRequest->student_id_type ?: 'ID')) }} {{ $pendingRequest->student_id_number }}
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
    @elseif ($user->affiliation_status === 'verified')
        <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">Afiliasi sudah terverifikasi</p>
            <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
                Data afiliasi aktif sudah disetujui super admin. Pengajuan baru tidak ditampilkan selama status masih terverifikasi.
            </p>
        </div>
    @else
        @if ($latestRejectedRequest)
            <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                <p class="text-sm font-semibold text-rose-900 dark:text-rose-100">Pengajuan terakhir ditolak</p>
                <p class="mt-1 text-sm text-rose-800 dark:text-rose-200">
                    {{ $latestRejectedRequest->rejection_reason ?: 'Silakan periksa kembali data afiliasi dan nomor identitas.' }}
                </p>
            </div>
        @endif

        <form wire:submit="submitAffiliationRequest" class="mt-5 space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="affiliation_type" class="form-label">Jenis Afiliasi</label>
                    <select id="affiliation_type" wire:model="affiliation_type" class="mt-1 form-input">
                        <option value="university">Kampus/Universitas</option>
                        <option value="school">Sekolah</option>
                        <option value="organization">Organisasi</option>
                        <option value="other">Lainnya</option>
                    </select>
                    @error('affiliation_type') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="student_id_type" class="form-label">Tipe Identitas</label>
                    <input id="student_id_type" wire:model="student_id_type" type="text" class="mt-1 form-input" placeholder="NIM, NIS, NIDN, ID Anggota">
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
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Submit Pengajuan
                </button>
            </div>
        </form>
    @endif
</section>
