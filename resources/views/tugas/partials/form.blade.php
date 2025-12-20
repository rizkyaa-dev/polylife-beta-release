@props([
    'action',
    'method' => 'POST',
    'tugas' => null,
    'submitLabel' => 'Simpan',
])

@php
    $labelClass = 'form-label';
    $inputClass = 'form-input';
@endphp

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if(!in_array(strtoupper($method), ['GET', 'POST']))
        @method($method)
    @endif

    <div>
        <label for="nama_tugas" class="{{ $labelClass }}">Nama Tugas</label>
        <input type="text"
               id="nama_tugas"
               name="nama_tugas"
               value="{{ old('nama_tugas', $tugas->nama_tugas ?? '') }}"
               class="mt-2 {{ $inputClass }} focus:border-emerald-400 focus:ring focus:ring-emerald-200/50"
               placeholder="Contoh: Laporan Interaksi Manusia & Komputer"
               required>
        @error('nama_tugas')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="deskripsi" class="{{ $labelClass }}">Deskripsi</label>
        <textarea id="deskripsi"
                  name="deskripsi"
                  rows="4"
                  class="mt-2 {{ $inputClass }} focus:border-emerald-400 focus:ring focus:ring-emerald-200/50"
                  placeholder="Ringkas detail tugas, ruang lingkup pekerjaan, atau catatan penting.">{{ old('deskripsi', $tugas->deskripsi ?? '') }}</textarea>
        @error('deskripsi')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="deadline" class="{{ $labelClass }}">Deadline</label>
            <input type="datetime-local"
                   id="deadline"
                   name="deadline"
                   value="{{ old('deadline', optional($tugas->deadline ?? now())->format('Y-m-d\TH:i')) }}"
                   class="mt-2 {{ $inputClass }} focus:border-emerald-400 focus:ring focus:ring-emerald-200/50"
                   required>
            @error('deadline')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex items-center gap-3 mt-6">
            <input type="checkbox"
                   id="status_selesai"
                   name="status_selesai"
                   value="1"
                   {{ old('status_selesai', $tugas->status_selesai ?? false) ? 'checked' : '' }}
                   class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:bg-slate-900 dark:border-slate-600">
            <label for="status_selesai" class="text-sm text-gray-800 dark:text-slate-100">Tandai selesai</label>
        </div>
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('tugas.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">← Kembali ke daftar tugas</a>
        <button type="submit"
                class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-1 dark:bg-emerald-500 dark:hover:bg-emerald-400">
            {{ $submitLabel }}
        </button>
    </div>
</form>
