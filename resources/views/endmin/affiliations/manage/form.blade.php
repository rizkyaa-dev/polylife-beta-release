{{-- resources/views/endmin/affiliations/manage/form.blade.php --}}
@extends('layouts.app')

@php
    $isEdit = $mode === 'edit';
    $aliasesText = old('aliases_text', implode("\n", $template->aliases ?? []));
    $affiliationTypeOptions = [
        '' => '- Pilih tipe -',
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
@endphp

@section('page_title', $isEdit ? 'Edit Afiliasi' : 'Tambah Afiliasi')
@section('page_description', 'Kelola nama resmi dan alias master afiliasi.')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Master Afiliasi</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $isEdit ? 'Edit Afiliasi' : 'Tambah Afiliasi' }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Perubahan nama akan disinkronkan ke user, admin assignment, dan target broadcast terkait.</p>
        </div>
        <a href="{{ route('endmin.affiliations.manage.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
            Kembali
        </a>
    </div>

    @if ($isEdit)
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">User Terhubung</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $template->users_count }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Admin Assignment</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $template->admin_assignments_count }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Target Broadcast</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $template->broadcast_targets_count }}</p>
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="POST" action="{{ $isEdit ? route('endmin.affiliations.manage.update', $template) : route('endmin.affiliations.manage.store') }}" class="space-y-5">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-4 md:grid-cols-[14rem_minmax(0,1fr)]">
                <div>
                    <label class="text-sm font-semibold text-slate-800 dark:text-slate-100">Tipe</label>
                    <select name="affiliation_type" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        @foreach ($affiliationTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('affiliation_type', $template->affiliation_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('affiliation_type')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-800 dark:text-slate-100">Nama Afiliasi Resmi</label>
                    <input type="text"
                           name="affiliation_name"
                           value="{{ old('affiliation_name', $template->affiliation_name) }}"
                           required
                           maxlength="160"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    @error('affiliation_name')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-800 dark:text-slate-100">Alias</label>
                <textarea name="aliases_text"
                          rows="4"
                          placeholder="Satu alias per baris, contoh: UI"
                          class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ $aliasesText }}</textarea>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Alias membantu super admin mengenali pengajuan dengan ejaan yang berbeda.</p>
                @error('aliases_text')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <input type="checkbox"
                       name="is_active"
                       value="1"
                       class="endmin-checkbox"
                       @checked(old('is_active', $template->is_active ?? true))>
                Aktif
            </label>

            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($isEdit)
                    <a href="{{ route('endmin.affiliations.manage.merge', $template) }}"
                       class="inline-flex items-center rounded-xl border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-500/30 dark:text-amber-200 dark:hover:bg-amber-500/10">
                        Merge
                    </a>
                @endif
                <a href="{{ route('endmin.affiliations.manage.index') }}"
                   class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Buat Afiliasi' }}
                </button>
            </div>
        </form>
    </div>

    @if ($isEdit)
        <div class="rounded-2xl border border-rose-100 bg-white p-6 shadow-sm dark:border-rose-500/20 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-500 dark:text-rose-300">Area Berbahaya</p>
            <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Hapus atau nonaktifkan afiliasi</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Jika afiliasi sudah dipakai, sistem akan menonaktifkan template agar riwayat user dan broadcast tetap aman.</p>

            <form method="POST"
                  action="{{ route('endmin.affiliations.manage.destroy', $template) }}"
                  class="mt-4"
                  onsubmit="return confirm('Hapus atau nonaktifkan afiliasi ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">
                    Hapus / Nonaktifkan
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
