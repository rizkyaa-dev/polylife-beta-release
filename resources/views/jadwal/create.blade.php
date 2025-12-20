@extends('layouts.app')

@section('page_title', 'Tambah Jadwal')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-800">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-300">Tambahkan agenda</p>
                <h2 class="text-2xl font-bold text-gray-900 mt-1 dark:text-slate-100">Buat Jadwal Baru</h2>
                <p class="text-sm text-gray-500 mt-2 dark:text-slate-400">
                    Simpan kegiatan pentingmu agar PolyLife bisa membantu mengingatkan aktivitas setiap hari.
                </p>
            </div>

            @include('jadwal.partials.form', [
                'action' => route('jadwal.store'),
                'method' => 'POST',
                'jadwal' => null,
                'submitLabel' => 'Simpan Jadwal',
                'matkuls' => $matkuls ?? collect(),
            ])
        </div>
    </div>
@endsection
