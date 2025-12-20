{{-- resources/views/keuangan/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Kelola Keuangan')

@section('content')
@php
    $guestMode = $guestMode ?? false;
    $totalPemasukan = $keuangans->where('jenis','pemasukan')->sum('nominal');
    $totalPengeluaran = $keuangans->where('jenis','pengeluaran')->sum('nominal');
    $saldo = $totalPemasukan - $totalPengeluaran;
    $statistikRoute = $guestMode ? route('guest.keuangan.statistik') : route('keuangan.statistik');
@endphp

<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold">Ringkasan bulan ini</p>
                <h2 class="text-2xl font-bold text-gray-900">Kelola arus kas harian</h2>
                <p class="text-sm text-gray-500 mt-1">Pantau pemasukan, pengeluaran, dan saldo tanpa perlu buka spreadsheet.</p>
            </div>
            <div class="flex flex-col w-full gap-2 sm:w-auto sm:flex-row">
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('keuangan.create', ['jenis' => 'pemasukan']) }}" @endif
                   class="w-full px-4 py-2 rounded-2xl text-center text-sm font-semibold text-white shadow {{ $guestMode ? 'bg-gray-300 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500' }}">
                    + Pemasukan
                </a>
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('keuangan.create', ['jenis' => 'pengeluaran']) }}" @endif
                   class="w-full px-4 py-2 rounded-2xl text-center text-sm font-semibold text-white shadow {{ $guestMode ? 'bg-gray-200 cursor-not-allowed text-gray-500' : 'bg-rose-500 hover:bg-rose-500/90' }}">
                    - Pengeluaran
                </a>
                <a href="{{ $statistikRoute }}"
                   class="w-full px-4 py-2 rounded-2xl border border-gray-100 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800/50"
                   title="Statistik Beta">
                    Statistik <span class="ml-1 text-[10px] align-baseline px-2 py-0.5 rounded-full bg-amber-500 text-white">Beta</span>
                </a>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-green-100 bg-green-50/80 p-4 dark:border-green-400/20 dark:bg-emerald-500/10">
                <p class="text-xs font-semibold text-green-600 uppercase dark:text-green-200">Total Pemasukan</p>
                <p class="mt-2 text-3xl font-bold text-green-800 dark:text-green-100">Rp {{ number_format($totalPemasukan,0,',','.') }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-rose-50/80 p-4 dark:border-rose-400/20 dark:bg-rose-500/10">
                <p class="text-xs font-semibold text-rose-600 uppercase dark:text-rose-200">Total Pengeluaran</p>
                <p class="mt-2 text-3xl font-bold text-rose-800 dark:text-rose-100">Rp {{ number_format($totalPengeluaran,0,',','.') }}</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                <p class="text-xs font-semibold text-gray-600 uppercase dark:text-slate-300">Saldo</p>
                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-slate-50">Rp {{ number_format($saldo,0,',','.') }}</p>
                <p class="text-xs text-gray-500 mt-1 dark:text-slate-400">Pemasukan - Pengeluaran</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Transaksi</h3>
                <p class="text-sm text-gray-500">Catat kebutuhan sehari-hari ataupun pemasukan dadakan.</p>
            </div>
            <a @if($guestMode) aria-disabled="true" @else href="{{ route('keuangan.create') }}" @endif
               class="inline-flex items-center justify-center rounded-2xl border border-gray-100 px-4 py-2 text-sm font-semibold {{ $guestMode ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800/50' }}">
                + Tambah Transaksi
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-100 dark:border-slate-800">
                        <th class="py-3 pr-4">Tanggal</th>
                        <th class="py-3 pr-4">Jenis</th>
                        <th class="py-3 pr-4">Kategori</th>
                        <th class="py-3 pr-4">Deskripsi</th>
                        <th class="py-3 pr-4 text-right">Nominal</th>
                        @unless($guestMode)
                            <th class="py-3 pl-4"></th>
                        @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($keuangans as $row)
                        <tr>
                            <td class="py-3 pr-4">{{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('Y-m-d') }}</td>
                            <td class="py-3 pr-4 capitalize">{{ $row->jenis }}</td>
                            <td class="py-3 pr-4">{{ $row->kategori ?? '-' }}</td>
                            <td class="py-3 pr-4">{{ $row->deskripsi ?? '-' }}</td>
                            <td class="py-3 pr-4 text-right">
                                Rp {{ number_format($row->nominal,0,',','.') }}
                            </td>
                            @unless($guestMode)
                                <td class="py-3 pl-4">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('keuangan.edit', $row->id) }}" class="px-2 py-1 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100">Edit</a>
                                        <form action="{{ route('keuangan.destroy', $row) }}" method="POST" onsubmit="return confirm('Hapus data ini?')">
                                            @csrf @method('DELETE')
                                            <button class="px-2 py-1 rounded bg-rose-50 text-rose-700 hover:bg-rose-100">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $guestMode ? 5 : 6 }}" class="py-6 text-center text-gray-500">Belum ada transaksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($guestMode)
            <div class="mt-4 rounded-xl border border-dashed border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-900">
                Mode tamu: data demo dibaca dari <code>storage/app/guest/workspace.json</code>. Simpan perubahan di sana untuk menyesuaikan contoh tanpa login.
            </div>
        @endif
    </div>
</div>
@endsection
