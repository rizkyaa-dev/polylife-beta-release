@extends('layouts.app')

@section('page_title', 'Edit Tugas')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6">
            <div>
                <p class="text-sm uppercase tracking-wide text-emerald-500 font-semibold">Produktivitas</p>
                <h2 class="text-2xl font-bold text-gray-900 mt-1">Edit Tugas</h2>
                <p class="text-sm text-gray-500 mt-2">
                    Perbarui detail tugas berikut lalu simpan perubahan.
                </p>
            </div>

            @include('tugas.partials.form', [
                'action' => route('tugas.update', $tugas),
                'method' => 'PUT',
                'tugas' => $tugas,
                'submitLabel' => 'Perbarui Tugas',
            ])
        </div>
    </div>
@endsection
