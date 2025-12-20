@extends('layouts.app')

@section('page_title', 'Profil Nilai Mutu Baru')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between dark:bg-slate-900 dark:border-slate-700 dark:shadow-slate-900/30">
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-slate-500">Nilai Mutu</p>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Buat standar huruf nilai kampusmu</h1>
                <p class="text-sm text-gray-500 dark:text-slate-300">
                    Data ini dipakai otomatis saat menyusun target IPS/IPK. Kamu bisa simpan beberapa profil untuk kampus atau kurikulum berbeda.
                </p>
            </div>
            <a href="{{ route('nilai-mutu.index') }}"
               class="inline-flex items-center rounded-2xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200">
                Kembali ke daftar profil
            </a>
        </div>

        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-700">
            <div class="space-y-1">
                <p class="text-base font-semibold text-gray-900 dark:text-white">Lengkapi informasi dasar nilai mutu</p>
                <p class="text-sm text-gray-500 dark:text-slate-300">Gunakan tombol preset jika ingin mengisi cepat berdasarkan standar umum.</p>
            </div>

            @include('nilai-mutu.partials.form', [
                'action' => route('nilai-mutu.store'),
                'method' => 'POST',
                'nilaiMutu' => $nilaiMutu,
                'submitLabel' => 'Simpan Profil Nilai Mutu',
            ])
        </div>
    </div>
@endsection
