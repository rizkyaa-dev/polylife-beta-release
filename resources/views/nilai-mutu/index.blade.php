@extends('layouts.app')

@section('page_title', 'Kelola Nilai Mutu')

@section('content')
    @php
        $guestMode = $guestMode ?? false;
        $ipkRoute = $guestMode ? route('guest.ipk.index') : route('ipk.index');
    @endphp
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-5 sm:p-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between dark:bg-slate-900 dark:border-slate-800">
            <div class="space-y-2">
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400 dark:text-slate-500">Nilai Mutu</p>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">Atur rentang nilai sekali, gunakan di semua target IP</h1>
                <p class="text-sm text-gray-500 dark:text-slate-400">
                    Simpan standar huruf A–E (plus/minus atau AB/BC) agar kalkulasi IPS/IPK dan simulasi target nilai lebih akurat.
                </p>
            </div>
            <div class="flex flex-col gap-3 w-full lg:max-w-xs">
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('nilai-mutu.create') }}" @endif
                   class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-white shadow {{ $guestMode ? 'bg-gray-300 cursor-not-allowed text-gray-600' : 'bg-indigo-600 hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400' }}">
                    {{ $guestMode ? 'Mode baca' : '+ Profil Nilai Mutu' }}
                </a>
                <a href="{{ $ipkRoute }}"
                   class="inline-flex items-center justify-center rounded-2xl border border-gray-200 dark:border-slate-700 px-5 py-3 text-sm font-semibold text-gray-700 dark:text-slate-200 hover:border-indigo-200 hover:text-indigo-600 dark:hover:border-indigo-500/60 dark:hover:text-indigo-400">
                    Kembali ke menu IP
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-3xl border border-indigo-100 bg-indigo-50 p-5 space-y-3 dark:border-indigo-500/20 dark:bg-indigo-500/10">
            <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">Cara kerja</p>
            <ol class="list-decimal list-inside text-sm text-indigo-900 dark:text-indigo-300 space-y-1.5">
                <li>Simpan rentang angka untuk setiap huruf nilai sesuai kebijakan kampus.</li>
                <li>Pilih format plus/minus (A, A-, B+) dan/atau format AB/BC sesuai kebutuhan.</li>
                <li>Tandai satu profil sebagai aktif agar otomatis dipakai di menu target IP.</li>
            </ol>
            <p class="text-xs text-indigo-800 dark:text-indigo-400">Kamu bisa memiliki banyak profil untuk beda kampus/konsentrasi.</p>
        </section>

        @php
            $formatNumber = static fn($value) => is_null($value) || $value === '' ? '—' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        @endphp

        <div class="space-y-4">
            @forelse($nilaiMutus as $profil)
                @php
                    $plusMinusRows = is_array($profil->grades_plus_minus) ? $profil->grades_plus_minus : [];
                    $abRows = is_array($profil->grades_ab) ? $profil->grades_ab : [];
                @endphp
                <article class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm space-y-5 dark:bg-slate-900 dark:border-slate-800">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-base font-semibold text-gray-900 dark:text-slate-100">
                                {{ $profil->kampus ?? 'Tanpa nama kampus' }} • {{ $profil->program_studi ?? 'Program studi umum' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">Kurikulum {{ $profil->kurikulum ?? '—' }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-4 py-1.5 text-xs font-semibold {{ $profil->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:border-emerald-500/30' : 'bg-gray-100 text-gray-500 border border-gray-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }}">
                            {{ $profil->is_active ? 'Profil aktif' : 'Cadangan' }}
                        </span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-gray-100 dark:border-slate-800 p-4 space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <p class="font-semibold text-gray-900 dark:text-slate-100">Format Plus / Minus</p>
                                <span class="text-xs text-gray-500 dark:text-slate-400">{{ count($plusMinusRows) }} rentang</span>
                            </div>
                            @if($plusMinusRows)
                                <div class="space-y-2 max-h-48 overflow-auto pr-1">
                                    @foreach($plusMinusRows as $row)
                                        <div class="flex items-center justify-between rounded-2xl bg-gray-50 dark:bg-slate-800/80 px-3 py-2 text-xs text-gray-700 dark:text-slate-300">
                                            <span class="font-semibold">{{ $row['letter'] ?? '-' }}</span>
                                            <span>{{ $formatNumber($row['min_score'] ?? null) }} - {{ $formatNumber($row['max_score'] ?? null) }}</span>
                                            <span class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ $formatNumber($row['grade_point'] ?? null) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-500 dark:text-slate-400 italic">Belum ada data huruf +/-. </p>
                            @endif
                        </div>
                        <div class="rounded-2xl border border-gray-100 dark:border-slate-800 p-4 space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <p class="font-semibold text-gray-900 dark:text-slate-100">Format AB / BC</p>
                                <span class="text-xs text-gray-500 dark:text-slate-400">{{ count($abRows) }} rentang</span>
                            </div>
                            @if($abRows)
                                <div class="space-y-2 max-h-48 overflow-auto pr-1">
                                    @foreach($abRows as $row)
                                        <div class="flex items-center justify-between rounded-2xl bg-gray-50 dark:bg-slate-800/80 px-3 py-2 text-xs text-gray-700 dark:text-slate-300">
                                            <span class="font-semibold">{{ $row['letter'] ?? '-' }}</span>
                                            <span>{{ $formatNumber($row['min_score'] ?? null) }} - {{ $formatNumber($row['max_score'] ?? null) }}</span>
                                            <span class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ $formatNumber($row['grade_point'] ?? null) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-500 dark:text-slate-400 italic">Belum ada data AB/BC.</p>
                            @endif
                        </div>
                    </div>

                    @if($profil->notes)
                        <p class="text-sm text-gray-600 dark:text-slate-300 border-l-2 border-indigo-100 dark:border-indigo-500/40 pl-3">{{ $profil->notes }}</p>
                    @endif

                    @unless($guestMode)
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('nilai-mutu.edit', $profil) }}"
                               class="inline-flex items-center rounded-2xl border border-gray-200 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-slate-200 hover:border-indigo-200 hover:text-indigo-600 dark:hover:border-indigo-500/60 dark:hover:text-indigo-400">
                                Edit Profil
                            </a>
                            <form action="{{ route('nilai-mutu.destroy', $profil) }}"
                                  method="POST"
                                  onsubmit="return confirm('Hapus profil nilai mutu ini? Data target IP yang memakainya akan memakai profil lain yang aktif.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center rounded-2xl border border-rose-200 dark:border-rose-500/30 px-4 py-2 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    @endunless
                </article>
            @empty
                <div class="bg-white border rounded-3xl shadow-sm p-8 text-center space-y-3 dark:bg-slate-900 dark:border-slate-800">
                    <p class="text-lg font-semibold text-gray-900 dark:text-slate-100">Belum ada profil nilai mutu</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Buat satu profil untuk menstandarkan rentang nilai dan bobot IP.</p>
                    <a @if($guestMode) aria-disabled="true" @else href="{{ route('nilai-mutu.create') }}" @endif
                       class="inline-flex items-center justify-center rounded-2xl px-5 py-2.5 text-sm font-semibold text-white shadow {{ $guestMode ? 'bg-gray-300 cursor-not-allowed text-gray-600' : 'bg-indigo-600 hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400' }}">
                        {{ $guestMode ? 'Mode baca' : '+ Profil Nilai Mutu' }}
                    </a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
