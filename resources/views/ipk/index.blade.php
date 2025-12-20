@extends('layouts.app')

@section('page_title', 'Rekap IPK')

@section('content')
    @php
        $guestMode = $guestMode ?? false;
        $formatGpa = static fn ($value) => is_null($value) ? '—' : number_format($value, 2);
        $semesterCount = $ipks->count();
        $latestSemester = $ipks->last()?->semester;
    @endphp

    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between dark:bg-slate-900 dark:border-slate-700 dark:shadow-slate-900/30">
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-slate-500">IPK Otomatis</p>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Masukkan IPS, lihat IPK kumulatif</h1>
                <p class="text-sm text-gray-500 dark:text-slate-300">Cukup simpan IPS tiap semester. IPK total dihitung otomatis dari semua semester yang ada.</p>
            </div>
            <div class="flex flex-col gap-2 w-full md:w-auto">
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('ipk.create') }}" @endif
                   class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-white shadow {{ $guestMode ? 'bg-gray-300 cursor-not-allowed text-gray-600' : 'bg-indigo-600 hover:bg-indigo-500' }}">
                    {{ $guestMode ? 'Mode baca' : '+ Tambah IPS' }}
                </a>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
                <p class="text-xs uppercase text-gray-400 dark:text-slate-500">IPK kumulatif</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $formatGpa($cumulativeIpk ?? null) }}</p>
                <p class="text-xs text-gray-500 dark:text-slate-300">Rata-rata dari semua IPS yang diinput.</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
                <p class="text-xs uppercase text-gray-400 dark:text-slate-500">Semester terisi</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $semesterCount }}</p>
                <p class="text-xs text-gray-500 dark:text-slate-300">Terakhir: {{ $latestSemester ?? '—' }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
                <p class="text-xs uppercase text-gray-400 dark:text-slate-500">IPS terakhir</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $formatGpa($latestIps ?? null) }}</p>
                <p class="text-xs text-gray-500 dark:text-slate-300">Dari semester paling baru.</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($ipks as $ipk)
                <article class="rounded-3xl border border-gray-200 bg-white/90 p-5 shadow-sm transition hover:border-indigo-200 dark:bg-slate-900/80 dark:border-slate-700 dark:hover:border-indigo-500/60">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-gray-400 dark:text-slate-500">Semester {{ $ipk->semester }}</p>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $ipk->academic_year ?? 'Tahun akademik belum diisi' }}
                            </h3>
                        </div>
                        <div class="flex gap-6 text-sm text-gray-600 dark:text-slate-300">
                            <div>
                                <p class="text-[11px] uppercase text-gray-400 dark:text-slate-500">IPS</p>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $formatGpa($ipk->ips_actual) }}</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-[11px] uppercase text-gray-400 dark:text-slate-500">IPK sampai sini</p>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $formatGpa($ipk->computed_running_ipk) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($ipk->remarks)
                        <p class="mt-3 text-sm text-gray-600 border-l-2 border-indigo-100 pl-3 dark:text-slate-300 dark:border-indigo-900">
                            {{ \Illuminate\Support\Str::limit($ipk->remarks, 140) }}
                        </p>
                    @endif

                    @unless($guestMode)
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('ipk.edit', $ipk) }}"
                               class="inline-flex items-center rounded-2xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400">
                                Edit IPS
                            </a>
                            <form action="{{ route('ipk.destroy', $ipk) }}" method="POST" onsubmit="return confirm('Hapus data semester ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center rounded-2xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/40 dark:hover:bg-rose-500/10">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    @endunless
                </article>
            @empty
                <div class="bg-white border rounded-3xl shadow-sm p-8 text-center space-y-2 dark:bg-slate-900 dark:border-slate-700">
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">Belum ada data IPS</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Tambah IPS pertama untuk melihat IPK kumulatif.</p>
                    <a @if($guestMode) aria-disabled="true" @else href="{{ route('ipk.create') }}" @endif
                       class="inline-flex items-center justify-center rounded-2xl px-5 py-2.5 text-sm font-semibold text-white shadow {{ $guestMode ? 'bg-gray-300 cursor-not-allowed text-gray-600' : 'bg-indigo-600 hover:bg-indigo-500' }}">
                        {{ $guestMode ? 'Mode baca' : '+ Tambah IPS' }}
                    </a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
