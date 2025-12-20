@extends('layouts.app')

@section('page_title', 'Edit IPS Semester')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-6 flex flex-col gap-3 dark:bg-slate-900 dark:border-slate-700 dark:shadow-slate-900/30">
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-slate-500">Rekap IPS</p>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Edit IPS semester {{ $ipk->semester }}</h1>
                <p class="text-sm text-gray-500 dark:text-slate-300">Perbarui nilai IPS atau catatan. IPK total akan mengikuti perubahan ini.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('ipk.index') }}"
                   class="inline-flex items-center rounded-2xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-indigo-400">
                    Kembali ke rekap
                </a>
            </div>
        </div>

        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-6 dark:bg-slate-900 dark:border-slate-700 dark:shadow-slate-900/30">
            @if ($errors->any())
                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-100">
                    <p class="font-semibold mb-2">Periksa lagi data berikut:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('ipk.partials.form', [
                'action' => route('ipk.update', $ipk),
                'method' => 'PUT',
                'ipk' => $ipk,
                'submitLabel' => 'Simpan perubahan',
            ])
        </div>
    </div>
@endsection
