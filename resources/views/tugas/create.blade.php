@extends('layouts.app')

@section('page_title', 'Tambah Tugas')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-800">
            <div>
                <p class="text-sm uppercase tracking-wide text-emerald-500 font-semibold dark:text-emerald-300">Produktivitas</p>
                <h2 class="text-2xl font-bold text-gray-900 mt-1 dark:text-slate-100">Tambah Tugas Baru</h2>
                <p class="text-sm text-gray-500 mt-2 dark:text-slate-400">
                    Catat tugas kuliah atau aktivitas personal agar tidak terlewat.
                </p>
            </div>

            @include('tugas.partials.form', [
                'action' => route('tugas.store'),
                'method' => 'POST',
                'tugas' => null,
                'submitLabel' => 'Simpan Tugas',
            ])
        </div>
    </div>
@endsection
