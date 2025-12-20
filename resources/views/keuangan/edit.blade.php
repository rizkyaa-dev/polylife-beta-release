@extends('layouts.app')

@section('page_title', 'Edit Transaksi')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Perbarui transaksi agar catatan keuangan tetap akurat</p>
                <h2 class="text-2xl font-semibold text-gray-900">Edit Transaksi</h2>
            </div>
            <a href="{{ route('keuangan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                &larr; Kembali
            </a>
        </div>

        <div class="bg-white border rounded-2xl shadow-sm p-6">
            <form action="{{ route('keuangan.update', $keuangan) }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="jenis" class="block text-sm font-medium text-gray-700">Jenis Transaksi</label>
                        <select name="jenis"
                                id="jenis"
                                class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                                required>
                            <option value="pemasukan" {{ old('jenis', $keuangan->jenis) === 'pemasukan' ? 'selected' : '' }}>Pemasukan</option>
                            <option value="pengeluaran" {{ old('jenis', $keuangan->jenis) === 'pengeluaran' ? 'selected' : '' }}>Pengeluaran</option>
                        </select>
                        @error('jenis')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="tanggal" class="block text-sm font-medium text-gray-700">Tanggal</label>
                        <input type="date"
                               name="tanggal"
                               id="tanggal"
                               value="{{ old('tanggal', \Illuminate\Support\Carbon::parse($keuangan->tanggal)->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                               required>
                        @error('tanggal')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="kategori" class="block text-sm font-medium text-gray-700">Kategori</label>
                        <input type="text"
                               name="kategori"
                               id="kategori"
                               value="{{ old('kategori', $keuangan->kategori) }}"
                               class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                               placeholder="Contoh: Makan, Gaji, Transport"
                               required>
                        @error('kategori')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nominal" class="block text-sm font-medium text-gray-700">Nominal</label>
                        <input type="number"
                               name="nominal"
                               id="nominal"
                               value="{{ old('nominal', $keuangan->nominal) }}"
                               min="0"
                               step="0.01"
                               class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                               placeholder="0"
                               required>
                        @error('nominal')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-gray-700">Deskripsi</label>
                    <textarea name="deskripsi"
                              id="deskripsi"
                              rows="4"
                              class="mt-1 block w-full rounded-2xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100"
                              placeholder="Catatan tambahan (opsional)">{{ old('deskripsi', $keuangan->deskripsi) }}</textarea>
                    @error('deskripsi')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('keuangan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
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
