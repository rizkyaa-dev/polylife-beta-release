@extends('layouts.app')

@section('page_title', 'Edit Jadwal')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-800">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-400">Perbarui agenda</p>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-slate-100 mt-1">Edit Jadwal</h2>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">
                    Lakukan penyesuaian pada kegiatan berikut kemudian simpan untuk memperbaharui catatanmu.
                </p>
            </div>

            @include('jadwal.partials.form', [
                'action' => route('jadwal.update', $jadwal),
                'method' => 'PUT',
                'jadwal' => $jadwal,
                'submitLabel' => 'Perbarui Jadwal',
                'matkuls' => $matkuls ?? collect(),
                'deleteUrl' => route('jadwal.confirm-delete', $jadwal),
            ])
        </div>
    </div>
@endsection
