{{-- resources/views/keuangan/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Kelola Keuangan')

@section('content')
@php
    $guestMode = $guestMode ?? false;
    $keuanganItems = $keuangans instanceof \Illuminate\Contracts\Pagination\Paginator ? collect($keuangans->items()) : $keuangans;
    $totalPemasukan = (float) data_get($summary ?? [], 'total_pemasukan', $keuanganItems->where('jenis','pemasukan')->sum('nominal'));
    $totalPengeluaran = (float) data_get($summary ?? [], 'total_pengeluaran', $keuanganItems->where('jenis','pengeluaran')->sum('nominal'));
    $saldo = $totalPemasukan - $totalPengeluaran;
    $statistikRoute = $guestMode ? route('guest.keuangan.statistik') : route('keuangan.statistik');
@endphp

<div class="space-y-6">
    {{-- Sub-Tabs Navigation --}}
    <div class="flex items-center justify-between">
        @include('keuangan.partials.nav-tabs', ['activeTab' => 'transaksi', 'guestMode' => $guestMode])
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold">Ringkasan Bulan Ini</p>
                <h3 class="text-xl font-bold text-gray-900 dark:text-slate-100">Arus Kas Berjalan</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Pantau total pemasukan, pengeluaran, dan saldo bersih bulan ini.</p>
            </div>
            <div class="flex flex-col w-full gap-2 sm:w-auto sm:flex-row sm:items-center">
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('keuangan.create', ['jenis' => 'pemasukan']) }}" @endif
                   class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap px-4 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm transition-all {{ $guestMode ? 'bg-gray-300 dark:bg-slate-700 text-gray-400 dark:text-slate-500 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98]' }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Pemasukan</span>
                </a>
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('keuangan.create', ['jenis' => 'pengeluaran']) }}" @endif
                   class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap px-4 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm transition-all {{ $guestMode ? 'bg-gray-200 dark:bg-slate-700 text-gray-400 dark:text-slate-500 cursor-not-allowed' : 'bg-rose-600 hover:bg-rose-500 active:scale-[0.98]' }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                    <span>Pengeluaran</span>
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Daftar Transaksi</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">Catat kebutuhan sehari-hari ataupun pemasukan dadakan.</p>
            </div>
            <a @if($guestMode) aria-disabled="true" @else href="{{ route('keuangan.create') }}" @endif
               class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold {{ $guestMode ? 'text-gray-400 dark:text-slate-500 cursor-not-allowed bg-gray-50 dark:bg-slate-800 border-gray-200 dark:border-slate-700' : 'text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800/50' }}">
                <svg class="w-4 h-4 text-gray-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Transaksi</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-100 dark:border-slate-800 text-gray-600 dark:text-slate-300 font-semibold">
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
                        <tr class="text-gray-800 dark:text-slate-200 hover:bg-gray-50/50 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 pr-4">{{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('Y-m-d') }}</td>
                            <td class="py-3 pr-4 capitalize">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $row->jenis === 'pemasukan' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' }}">
                                    {{ $row->jenis }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">{{ $row->kategori ?? '-' }}</td>
                            <td class="py-3 pr-4">{{ $row->deskripsi ?? '-' }}</td>
                            <td class="py-3 pr-4 text-right font-semibold {{ $row->jenis === 'pemasukan' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $row->jenis === 'pemasukan' ? '+' : '-' }} Rp {{ number_format($row->nominal,0,',','.') }}
                            </td>
                            @unless($guestMode)
                                <td class="py-3 pl-4">
                                    <div class="flex items-center gap-2 justify-end">
                                        <a href="{{ route('keuangan.edit', $row->id) }}" class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20 font-medium text-xs">Edit</a>
                                        <form action="{{ route('keuangan.destroy', $row) }}" method="POST" onsubmit="return confirm('Hapus data ini?')">
                                            @csrf @method('DELETE')
                                            <button class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20 font-medium text-xs">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $guestMode ? 5 : 6 }}" class="py-6 text-center text-gray-500 dark:text-slate-400">Belum ada transaksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($keuangans instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="mt-4">
                {{ $keuangans->links() }}
            </div>
        @endif

        @if($guestMode)
            <div class="mt-4 rounded-xl border border-dashed border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-900 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                Mode tamu: data demo dibaca dari <code>storage/app/guest/workspace.json</code>. Simpan perubahan di sana untuk menyesuaikan contoh tanpa login.
            </div>
        @endif
    </div>
</div>
@endsection
