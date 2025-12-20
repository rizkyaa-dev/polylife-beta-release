@extends('layouts.app')

@section('page_title', 'Ubah Profil Nilai Mutu')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between dark:bg-slate-900 dark:border-slate-700 dark:shadow-slate-900/30">
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-slate-500">Nilai Mutu</p>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Perbarui profil nilai mutu</h1>
                <p class="text-sm text-gray-500 dark:text-slate-300">
                    Jika kampus mengubah kebijakan rentang nilai, cukup ubah data di sini. Target IPS/IPK akan otomatis menyesuaikan.
                </p>
            </div>
            <a href="{{ route('nilai-mutu.index') }}"
               class="inline-flex items-center rounded-2xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200">
                Kembali ke daftar profil
            </a>
        </div>

        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-700">
            <div class="space-y-1">
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    Mengubah profil: {{ $nilaiMutu->kampus ?? 'Tanpa nama kampus' }} • {{ $nilaiMutu->program_studi ?? 'Program studi' }}
                </p>
                <p class="text-sm text-gray-500 dark:text-slate-300">Pastikan tidak ada rentang yang saling tumpang tindih untuk memudahkan kalkulasi.</p>
            </div>

            @include('nilai-mutu.partials.form', [
                'action' => route('nilai-mutu.update', $nilaiMutu),
                'method' => 'PUT',
                'nilaiMutu' => $nilaiMutu,
                'submitLabel' => 'Perbarui Profil',
            ])
        </div>
    </div>
@endsection
