@extends('layouts.app')

@section('page_title', 'Edit Matkul')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold">Data Akademik</p>
                <h2 class="text-2xl font-bold text-gray-900 mt-1">Edit Mata Kuliah</h2>
                <p class="text-sm text-gray-500 mt-2">
                    Sesuaikan informasi matkul di bawah ini lalu simpan perubahan.
                </p>
            </div>

            @include('matkul.partials.form', [
                'action' => route('matkul.update', $matkul),
                'method' => 'PUT',
                'matkul' => $matkul,
                'submitLabel' => 'Perbarui Matkul',
            ])
        </div>
    </div>
@endsection
