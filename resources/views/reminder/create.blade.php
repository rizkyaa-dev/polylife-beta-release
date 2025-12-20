@extends('layouts.app')

@section('page_title', 'Tambah Reminder')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-4 dark:bg-slate-900 dark:border-slate-800">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-300">Pengingat baru</p>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Tambah Reminder</h1>
                <p class="text-sm text-gray-500 mt-1 dark:text-slate-400">Tautkan reminder ke to-do, tugas, jadwal, atau kegiatan supaya PolyLife bisa mengingatkan tepat waktu.</p>
            </div>

            @include('reminder.partials.form', [
                'action' => route('reminder.store'),
                'method' => 'POST',
                'submitLabel' => 'Simpan Reminder',
                'reminder' => null,
                'selectedTarget' => $selectedTarget ?? 'todolist',
                'todolists' => $todolists,
                'tugasList' => $tugasList,
                'jadwals' => $jadwals,
                'kegiatans' => $kegiatans,
            ])
        </div>
    </div>
@endsection
