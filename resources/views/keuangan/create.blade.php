@extends('layouts.app')

@section('page_title', ucfirst($jenis) . ' Keuangan')

@section('content')
<div class="max-w-3xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-3xl border dark:bg-slate-900 dark:border-slate-800">
    <div class="p-6 sm:p-8 text-gray-900 dark:text-slate-100">
        <form action="{{ route('keuangan.store') }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="jenis" value="{{ $jenis }}">

            <div>
                <label for="kategori" class="form-label">Kategori</label>
                <input type="text" name="kategori" id="kategori" class="mt-1 form-input" required>
            </div>

            <div>
                <label for="deskripsi" class="form-label">Deskripsi</label>
                <textarea name="deskripsi" id="deskripsi" rows="3" class="mt-1 form-input" placeholder="Tambahkan catatan singkat"></textarea>
            </div>

            <div>
                <label for="nominal" class="form-label">Nominal</label>
                <input type="number" name="nominal" id="nominal" class="mt-1 form-input" required>
            </div>

            <div>
                <label for="tanggal" class="form-label">Tanggal</label>
                <input type="date" name="tanggal" id="tanggal" value="{{ now()->toDateString() }}" class="mt-1 form-input" required>
            </div>

            <div class="flex items-center justify-end pt-2">
                <button type="submit" class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
