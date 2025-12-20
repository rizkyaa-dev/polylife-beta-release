@extends('layouts.app')

@section('page_title', 'Batch Process Matkul (Beta)')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-800">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-300">Eksperimen</p>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Batch Process Matkul (Beta)</h2>
                <p class="text-sm text-gray-500 mt-2 dark:text-slate-400">
                    Tempelkan data dari portal akademik (format tabel / salinan Excel) lalu sistem akan mencoba membuat / memperbarui data matkul secara otomatis.
                </p>
                <p class="mt-3 text-xs font-medium text-emerald-600 dark:text-emerald-300">
                    Kolom jadwal (hari, kelas, jam, ruangan) sekarang mengikuti format string panjang yang dipisahkan tanda titik koma (;), sesuai migrasi terbaru.
                </p>
            </div>

            @if(session('batch_result'))
                @php($batchResult = session('batch_result'))
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-100">
                    <p class="font-semibold">
                        {{ $batchResult['created'] }} matkul baru ditambahkan, {{ $batchResult['updated'] }} diperbarui dari total {{ $batchResult['total'] }} baris terbaca.
                    </p>
                    @if (!empty($batchResult['failed']))
                        <p class="mt-2">Beberapa baris dilewati:</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                            @foreach($batchResult['failed'] as $row)
                                <li><span class="font-semibold">{{ $row['kode'] ?? 'Kode tidak diketahui' }}:</span> {{ $row['reason'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <form action="{{ route('matkul.batch.import') }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <div class="flex items-center justify-between">
                        <label for="raw_data" class="form-label">Tempel Data Tabel</label>
                        <span class="text-xs text-gray-400">Gunakan urutan kolom: No, Kode, Mata Kuliah, Nama Kelas, SKS, Jadwal, Keterangan</span>
                    </div>
                    <textarea id="raw_data" name="raw_data" rows="10"
                              class="mt-2 form-input font-mono text-xs leading-5"
                              placeholder="Tempelkan data dari portal/kertas kerja di sini..." required>{{ old('raw_data') }}</textarea>
                    @error('raw_data')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="default_semester" class="form-label">Semester Default</label>
                        <input type="number" id="default_semester" name="default_semester"
                               value="{{ old('default_semester', 1) }}"
                               min="1" max="14"
                               class="mt-2 form-input" required>
                        @error('default_semester')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="default_dosen" class="form-label">Nama Dosen (Default)</label>
                        <input type="text" id="default_dosen" name="default_dosen"
                               value="{{ old('default_dosen', 'Belum ditentukan') }}"
                               class="mt-2 form-input" required>
                        @error('default_dosen')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="default_warna_label" class="form-label">Warna Label Default</label>
                        <input type="color" id="default_warna_label" name="default_warna_label"
                               value="{{ old('default_warna_label', '#2563eb') }}"
                               class="mt-2 h-12 w-full rounded-2xl border border-gray-200 dark:border-slate-700 dark:bg-slate-900" required>
                        @error('default_warna_label')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="catatan_prefix" class="form-label">Catatan Tambahan (opsional)</label>
                        <input type="text" id="catatan_prefix" name="catatan_prefix"
                               value="{{ old('catatan_prefix') }}"
                               class="mt-2 form-input"
                               placeholder="Misal: Data import Semester Genap">
                        <p class="mt-1 text-xs text-gray-500">Jika diisi akan digabung dengan jadwal lengkap pada kolom catatan.</p>
                        @error('catatan_prefix')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-dashed border-gray-200 p-4 dark:border-slate-700">
                        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold mb-2 dark:text-slate-400">Format penyimpanan baru</p>
                        <div class="grid gap-3 sm:grid-cols-2 text-xs text-gray-600 dark:text-slate-400">
                            <div class="space-y-1">
                                <p class="font-semibold text-gray-800 dark:text-slate-200">Hari</p>
                                <p class="font-mono bg-white/70 dark:bg-slate-900/40 rounded-xl px-3 py-1.5 text-[11px]">
                                    senin;rabu;jumat;
                                </p>
                                <p>Satu matkul bisa tersimpan di beberapa hari kuliah.</p>
                            </div>
                            <div class="space-y-1">
                                <p class="font-semibold text-gray-800 dark:text-slate-200">Jam & Ruangan</p>
                                <p class="font-mono bg-white/70 dark:bg-slate-900/40 rounded-xl px-3 py-1.5 text-[11px]">
                                    07:30;09:10; <span class="text-gray-400">/</span> LAB.MM;R.410;
                                </p>
                                <p>Jam mulai, jam selesai, dan ruangan mengikuti urutan baris jadwal yang ditempel.</p>
                            </div>
                            <div class="space-y-1">
                                <p class="font-semibold text-gray-800 dark:text-slate-200">Kelas</p>
                                <p class="font-mono bg-white/70 dark:bg-slate-900/40 rounded-xl px-3 py-1.5 text-[11px]">
                                    RPL-3A;RPL-3B;
                                </p>
                                <p>Anda bisa menggabungkan banyak kelas pada kolom nama kelas.</p>
                            </div>
                            <div class="space-y-1">
                                <p class="font-semibold text-gray-800 dark:text-slate-200">Catatan</p>
                                <p class="font-mono bg-white/70 dark:bg-slate-900/40 rounded-xl px-3 py-1.5 text-[11px]">
                                    Jadwal lengkap tersimpan otomatis.
                                </p>
                                <p>Semua baris jadwal tetap ditulis ulang pada catatan agar mudah dilihat.</p>
                            </div>
                        </div>
                        <p class="mt-3 text-[11px] text-indigo-500 dark:text-indigo-300">Tidak perlu memisahkan manual; sistem akan menggabungkannya sesuai format di atas.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-gray-500">
                            Sistem tetap memakai jadwal pertama pada tiap baris sebagai referensi utama, namun seluruh jadwal disimpan pada string multi-nilai.
                        </p>
                        <button type="submit"
                                class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1">
                            Proses & Simpan
                        </button>
                    </div>
                </div>
            </form>

            <div class="rounded-2xl bg-gray-50 p-4 text-xs text-gray-500 dark:bg-slate-800/60 dark:text-slate-400">
                <p>Tips: Salin seluruh tabel (termasuk header) dari portal akademik, pastikan antar kolom dipisahkan tab atau dua spasi. Jadwal bertumpuk boleh dalam beberapa baris.</p>
                <p class="mt-1">Contoh format:</p>
                <pre class="mt-2 whitespace-pre-wrap rounded-xl bg-white/70 p-3 text-[11px] text-gray-700 dark:bg-slate-900/40 dark:text-slate-300">No	Kode	Mata Kuliah	Nama Kelas	SKS	Jadwal	Keterangan
1	RPLKK4093	PROYEK 1	24	3	Kamis, 10:00 s.d 11:40 @ LAB.PMRGMN
Jumat, 09:10 s.d 10:50 @ LAB.TUK</pre>
            </div>
        </div>
    </div>
@endsection
