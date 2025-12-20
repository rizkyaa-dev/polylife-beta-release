@extends('layouts.app')

@section('page_title', 'Edit Catatan')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Perbarui isi catatanmu</p>
                <h2 class="text-2xl font-semibold text-gray-900">Edit Catatan</h2>
            </div>
            <a href="{{ route('catatan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                &larr; Kembali
            </a>
        </div>

        <div class="bg-white border rounded-2xl shadow-sm p-6">
            <form action="{{ route('catatan.update', $catatan) }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="judul" class="block text-sm font-medium text-gray-700">Judul</label>
                    <input type="text"
                           name="judul"
                           id="judul"
                           value="{{ old('judul', $catatan->judul) }}"
                           class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                           placeholder="Contoh: Ringkasan materi pertemuan"
                           required>
                    @error('judul')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="tanggal" class="block text-sm font-medium text-gray-700">Tanggal</label>
                    <input type="date"
                           name="tanggal"
                           id="tanggal"
                           value="{{ old('tanggal', \Illuminate\Support\Carbon::parse($catatan->tanggal)->format('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                           required>
                    @error('tanggal')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="isi" class="block text-sm font-medium text-gray-700">Isi Catatan</label>
                    <textarea name="isi"
                              id="isi"
                              rows="8"
                              class="mt-1 block w-full rounded-2xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                              placeholder="Tulis isi catatanmu di sini..."
                              required>{{ old('isi', $catatan->isi) }}</textarea>
                    @error('isi')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('catatan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-white font-semibold shadow"
                            style="background-color: #1261DE;">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
