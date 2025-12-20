@extends('layouts.app')

@section('page_title', 'Tambah Catatan')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-slate-400">Catat ide atau hal pentingmu</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">Catatan Baru</h2>
            </div>
            <a href="{{ route('catatan.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
                &larr; Kembali
            </a>
        </div>

        <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
            <form action="{{ route('catatan.store') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="judul" class="form-label">Judul</label>
                    <input type="text"
                           name="judul"
                           id="judul"
                           value="{{ old('judul') }}"
                           class="mt-1 form-input"
                           placeholder="Contoh: Ringkasan materi pertemuan"
                           required>
                    @error('judul')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="tanggal" class="form-label">Tanggal</label>
                    <input type="date"
                           name="tanggal"
                           id="tanggal"
                           value="{{ old('tanggal', now()->format('Y-m-d')) }}"
                           class="mt-1 form-input"
                           required>
                    @error('tanggal')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="isi" class="form-label">Isi Catatan</label>
                    <textarea name="isi"
                              id="isi"
                              rows="8"
                              class="mt-1 form-input"
                              placeholder="Tulis isi catatanmu di sini..."
                              required>{{ old('isi') }}</textarea>
                    @error('isi')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('catatan.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold shadow hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        Simpan Catatan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
