@props([
    'action',
    'method' => 'POST',
    'matkul' => null,
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

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="kode" class="{{ $labelClass }}">Kode Matkul</label>
            <input type="text" id="kode" name="kode"
                   value="{{ old('kode', $matkul->kode ?? '') }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   placeholder="Contoh: RPLKU3112" required>
            @error('kode') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="nama" class="{{ $labelClass }}">Nama Matkul</label>
            <input type="text" id="nama" name="nama"
                   value="{{ old('nama', $matkul->nama ?? '') }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   placeholder="Interaksi Manusia & Komputer" required>
            @error('nama') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="kelas" class="{{ $labelClass }}">Kelas</label>
            <input type="text" id="kelas" name="kelas"
                   value="{{ old('kelas', $matkul->kelas ?? '') }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   placeholder="A;B;C;" required>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Pisahkan beberapa kelas dengan tanda titik koma, contoh: <span class="font-semibold">A;B;C;</span></p>
        </div>
        <div>
            <label for="semester" class="{{ $labelClass }}">Semester</label>
            <input type="number" id="semester" name="semester"
                   value="{{ old('semester', $matkul->semester ?? '') }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   min="1" max="14" required>
        </div>
        <div>
            <label for="sks" class="{{ $labelClass }}">SKS</label>
            <input type="number" id="sks" name="sks"
                   value="{{ old('sks', $matkul->sks ?? 2) }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   min="1" max="6" required>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="dosen" class="{{ $labelClass }}">Dosen</label>
            <input type="text" id="dosen" name="dosen"
                   value="{{ old('dosen', $matkul->dosen ?? '') }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   required>
        </div>
        <div>
            <label for="ruangan" class="{{ $labelClass }}">Ruangan</label>
            <input type="text" id="ruangan" name="ruangan"
                   value="{{ old('ruangan', $matkul->ruangan ?? '') }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   placeholder="A101;Lab 3;" required>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Gunakan format string panjang, pisahkan ruangan dengan <span class="font-semibold">;</span> dan akhiri dengan tanda tersebut.</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="hari" class="{{ $labelClass }}">Hari Kuliah</label>
            <textarea id="hari" name="hari" rows="2"
                      class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                      placeholder="senin;rabu;jumat;" required>{{ old('hari', $matkul->hari ?? '') }}</textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Sesuaikan urutan hari dengan jadwal yang ada. Contoh: <span class="font-semibold">senin;rabu;jumat;</span></p>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="jam_mulai" class="{{ $labelClass }}">Jam Mulai</label>
                <input type="text" id="jam_mulai" name="jam_mulai"
                       value="{{ old('jam_mulai', $matkul->jam_mulai ?? '') }}"
                       class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                       placeholder="07:30;10:15;" required>
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Gunakan format HH:MM lalu pisahkan dengan tanda <span class="font-semibold">;</span>.</p>
            </div>
            <div>
                <label for="jam_selesai" class="{{ $labelClass }}">Jam Selesai</label>
                <input type="text" id="jam_selesai" name="jam_selesai"
                       value="{{ old('jam_selesai', $matkul->jam_selesai ?? '') }}"
                       class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                       placeholder="09:00;12:00;" required>
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Pastikan jumlah item sama dengan jam mulai dan dipisah <span class="font-semibold">;</span>.</p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="warna_label" class="{{ $labelClass }}">Warna Label</label>
            <input type="color" id="warna_label" name="warna_label"
                   value="{{ old('warna_label', $matkul->warna_label ?? '#8181FF') }}"
                   class="mt-2 h-10 w-full rounded-2xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-200/50 dark:border-slate-700 dark:bg-slate-900"
                   required>
        </div>
        <div>
            <label for="catatan" class="{{ $labelClass }}">Catatan</label>
            <textarea id="catatan" name="catatan" rows="3"
                      class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                      placeholder="Catatan tambahan" required>{{ old('catatan', $matkul->catatan ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('matkul.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">← Kembali ke daftar matkul</a>
        <button type="submit"
                class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 dark:bg-indigo-500 dark:hover:bg-indigo-400">
            {{ $submitLabel }}
        </button>
    </div>
</form>
