@extends('layouts.app')

@section('page_title', 'Tambah To-Do')

@section('content')
    <div class="max-w-xl mx-auto bg-white border rounded-2xl shadow-sm p-6 space-y-6 dark:bg-slate-900 dark:border-slate-800">
        <div>
            <p class="text-sm text-gray-500 dark:text-slate-400">Buat rencana baru</p>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">Tambah Tugas</h2>
            <p class="text-sm text-gray-500 mt-1 dark:text-slate-400">Isi nama tugas dan tandai selesai jika sudah rampung.</p>
        </div>

        <form action="{{ route('todolist.store') }}" method="POST" class="space-y-5">
            @csrf
            @php
                $labelClass = 'form-label';
                $inputClass = 'form-input';
                $helperClass = 'form-helper';
            @endphp
            <div>
                <label for="nama_item" class="{{ $labelClass }}">Nama Tugas</label>
                <input type="text"
                       name="nama_item"
                       id="nama_item"
                       value="{{ old('nama_item') }}"
                       class="mt-1 {{ $inputClass }}"
                       placeholder="Contoh: Kerjakan laporan praktikum"
                       required>
                @error('nama_item')
                    <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 rounded-2xl border border-gray-200 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/60">
                <input type="checkbox"
                       name="status"
                       id="status"
                       value="1"
                       class="h-4 w-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-600"
                       {{ old('status') ? 'checked' : '' }}>
                <div>
                    <label for="status" class="text-sm font-medium text-gray-900 dark:text-slate-100">Tandai langsung selesai</label>
                    <p class="text-xs text-gray-500 dark:text-slate-400">Centang jika tugas ini sudah diselesaikan.</p>
                </div>
            </div>

            <div class="space-y-3 rounded-2xl border border-dashed border-indigo-200 p-4 dark:border-indigo-500/40 dark:bg-slate-900/40">
                <div class="flex items-center gap-3">
                    <input type="checkbox"
                           name="reminder_enabled"
                           id="reminder_enabled"
                           value="1"
                           class="h-4 w-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-600"
                           {{ old('reminder_enabled') ? 'checked' : '' }}>
                    <div>
                        <label for="reminder_enabled" class="text-sm font-semibold text-gray-900 dark:text-slate-100">Setel reminder</label>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Aktifkan agar kamu mendapatkan pengingat tepat waktu.</p>
                    </div>
                </div>

                <div id="reminder_fields" class="space-y-3 {{ old('reminder_enabled') ? '' : 'hidden' }}">
                    <label class="{{ $labelClass }}">Waktu Pengingat</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="relative flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer dark:border-slate-700 dark:bg-slate-900/60">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3M5 11h14M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </span>
                            <div class="flex-1">
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-400">Tanggal</p>
                                <input type="date"
                                       name="reminder_date"
                                       id="reminder_date_input"
                                       value="{{ old('reminder_date') }}"
                                       class="mt-1 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-slate-100">
                                @error('reminder_date')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </label>
                        <label class="relative flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer dark:border-slate-700 dark:bg-slate-900/60">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <div class="flex-1">
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-400">Jam</p>
                                <input type="time"
                                       name="reminder_time"
                                       id="reminder_time_input"
                                       value="{{ old('reminder_time') }}"
                                       class="mt-1 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-slate-100">
                                @error('reminder_time')
                                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-slate-400">Sistem akan otomatis menggabungkan tanggal dan jam saat disimpan.</p>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <a href="{{ route('todolist.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
                    &larr; Kembali ke daftar
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold shadow hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Simpan Tugas
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkbox = document.getElementById('reminder_enabled');
            const fields = document.getElementById('reminder_fields');
            const reminderDateInput = document.getElementById('reminder_date_input');
            const reminderTimeInput = document.getElementById('reminder_time_input');
            const form = document.querySelector('form');

            const toggleFields = () => {
                if (!checkbox || !fields) return;
                fields.classList.toggle('hidden', !checkbox.checked);
                if (!checkbox.checked) {
                    reminderDateInput.value = '';
                    reminderTimeInput.value = '';
                }
            };

            checkbox?.addEventListener('change', toggleFields);
            toggleFields();

            form?.addEventListener('submit', (event) => {
                if (!checkbox?.checked) return;
                const dateValue = reminderDateInput?.value;
                if (!dateValue) {
                    alert('Silakan pilih tanggal reminder.');
                    event.preventDefault();
                    reminderDateInput?.focus();
                }
            });
        });
    </script>
@endpush
