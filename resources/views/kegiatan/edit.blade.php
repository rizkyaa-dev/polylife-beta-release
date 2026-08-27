@extends('layouts.app')

@section('page_title', 'Edit Kegiatan')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-800">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-400">Agenda Detail</p>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-slate-100 mt-1">Edit Kegiatan</h2>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">
                    Sesuaikan informasi kegiatan di bawah ini lalu simpan perubahan.
                </p>
            </div>

            @include('kegiatan.partials.form', [
                'action' => route('kegiatan.update', $kegiatan),
                'method' => 'PUT',
                'kegiatan' => $kegiatan,
                'jadwals' => $jadwals,
                'submitLabel' => 'Perbarui Kegiatan',
            ])
        </div>
    </div>
@endsection
