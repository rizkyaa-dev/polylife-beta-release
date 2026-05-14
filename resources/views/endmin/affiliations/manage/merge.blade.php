{{-- resources/views/endmin/affiliations/manage/merge.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Merge Afiliasi')
@section('page_description', 'Gabungkan template afiliasi duplikat ke template utama.')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-500 dark:text-amber-300">Master Afiliasi</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">Merge Afiliasi</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pindahkan semua relasi dari template sumber ke template tujuan, lalu nonaktifkan sumber.</p>
        </div>
        <a href="{{ route('endmin.affiliations.manage.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
            Kembali
        </a>
    </div>

    <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        Merge bersifat permanen untuk relasi aktif. Template sumber tidak dihapus, tetapi dinonaktifkan dan diberi penanda merged untuk menjaga riwayat.
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Template Sumber</p>
        <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $template->affiliation_name }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $template->affiliation_type ?: '-' }} &middot; {{ $template->normalized_name ?: '-' }}</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">User</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $template->users_count }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Admin</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $template->admin_assignments_count }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Broadcast</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $template->broadcast_targets_count }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Request</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $template->requests_count }}</p>
            </div>
        </div>

        <form method="POST"
              action="{{ route('endmin.affiliations.manage.merge.store', $template) }}"
              class="mt-6 space-y-4"
              onsubmit="return confirm('Gabungkan template ini ke template tujuan?');">
            @csrf
            <div>
                <label class="text-sm font-semibold text-slate-800 dark:text-slate-100">Template Tujuan</label>
                <select name="target_template_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Pilih template tujuan</option>
                    @foreach ($targets as $target)
                        <option value="{{ $target->id }}" @selected(old('target_template_id') == $target->id)>
                            {{ $target->affiliation_name }}{{ $target->affiliation_type ? ' - '.$target->affiliation_type : '' }}
                        </option>
                    @endforeach
                </select>
                @error('target_template_id')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <a href="{{ route('endmin.affiliations.manage.edit', $template) }}"
                   class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                    Merge Template
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
