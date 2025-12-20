@extends('layouts.app')

@section('page_title', 'Dashboard (Guest)')
@section('page_description', 'Pratinjau workspace lengkap dengan data demo—semua menu bisa dilihat tanpa login.')

@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $stats = $quickStats ?? [];
    $today = isset($todayDate) ? Carbon::parse($todayDate) : Carbon::today();
    $todayDayIndex = $today->dayOfWeek;
    $indexToDayKey = [
        0 => 'minggu',
        1 => 'senin',
        2 => 'selasa',
        3 => 'rabu',
        4 => 'kamis',
        5 => 'jumat',
        6 => 'sabtu',
    ];
    $todayDayKey = $indexToDayKey[$todayDayIndex] ?? Str::lower($today->translatedFormat('l'));

    $formatLegacyTime = function ($value) {
        if (!$value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        if (str_contains($string, ';')) {
            $string = explode(';', $string)[0];
        }

        return $string;
    };

    $normalizeTimeForSort = function ($value) {
        if (!$value) {
            return '99:99';
        }

        $candidate = strtolower((string) $value);
        $candidate = str_replace('.', ':', $candidate);
        if (preg_match('/(\d{1,2})(?:[:](\d{1,2}))?/', $candidate, $matches)) {
            $hour = (int) $matches[1];
            $minute = isset($matches[2]) ? (int) $matches[2] : 0;
            $hour = max(0, min(23, $hour));
            $minute = max(0, min(59, $minute));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return '99:99';
    };

    $hasMatkulDayData = function ($matkul) {
        if (!$matkul) return false;
        if (method_exists($matkul, 'scheduleDays')) {
            return $matkul->scheduleDays()->isNotEmpty();
        }
        $raw = (string) ($matkul->hari ?? '');
        return trim($raw) !== '';
    };
@endphp

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border-4 border-slate-900 bg-violet-100/70 p-5 shadow-[8px_8px_0_0_rgba(15,23,42,0.85)]">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-900">IPK (demo)</p>
            <p class="mt-2 text-3xl font-black text-slate-900">{{ $stats['ipk'] ?? '3.50' }}</p>
            <p class="text-sm text-slate-700">Hitung dari IPS contoh di mode tamu.</p>
        </div>
        <div class="rounded-2xl border-4 border-slate-900 bg-white p-5 shadow-[8px_8px_0_0_rgba(15,23,42,0.85)]">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-900">Saldo (demo)</p>
            <p class="mt-2 text-2xl font-black text-slate-900">
                Rp {{ isset($ringkasanKeuangan['saldo_bulan_ini']) ? number_format($ringkasanKeuangan['saldo_bulan_ini'],0,',','.') : '0' }}
            </p>
            <p class="text-sm text-slate-700">Bulan {{ $ringkasanKeuangan['bulan_label'] ?? $today->translatedFormat('F Y') }}</p>
        </div>
        <div class="rounded-2xl border-4 border-slate-900 bg-slate-900 text-violet-100 p-5 shadow-[8px_8px_0_0_rgba(15,23,42,0.85)]">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-violet-100">Tugas (demo)</p>
            <p class="mt-2 text-2xl font-black">
                {{ $stats['tugas']['aktif'] ?? 0 }} aktif
                <span class="text-sm text-violet-200">/ {{ $stats['tugas']['selesai'] ?? 0 }} selesai</span>
            </p>
            <p class="text-sm text-violet-200">To-do & reminder contoh.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        {{-- Jadwal Hari Ini --}}
        <section class="bg-white rounded-2xl shadow-sm border p-5 h-full flex flex-col dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Agenda Hari Ini</p>
                    <h2 class="text-lg font-semibold text-gray-900">Jadwal Kuliah & Kegiatan</h2>
                </div>
                <a href="{{ route('guest.jadwal.index') }}" class="text-sm text-indigo-600 hover:underline">Lihat kalender</a>
            </header>

            @php
                $jadwalCollection = collect($jadwalHariIni ?? []);
            @endphp

            @if($jadwalCollection->isNotEmpty())
                @foreach($jadwalCollection as $agenda)
                    @php
                        $startLabel = optional($agenda->tanggal_mulai)->translatedFormat('d M Y') ?? '-';
                        $endLabel = optional($agenda->tanggal_selesai)->translatedFormat('d M Y');
                        $rangeLabel = $endLabel && $endLabel !== $startLabel ? $startLabel . ' - ' . $endLabel : $startLabel;

                        $scheduleEntries = collect($agenda->matkul_details ?? [])
                            ->flatMap(function ($matkul) use ($formatLegacyTime, $normalizeTimeForSort, $todayDayKey) {
                                if (!$matkul) {
                                    return collect();
                                }

                                $entries = method_exists($matkul, 'scheduleEntries') ? collect($matkul->scheduleEntries()) : collect();
                                if ($entries->isEmpty()) {
                                    $entries = collect([[
                                        'hari' => method_exists($matkul, 'primaryDay') ? $matkul->primaryDay() : ($matkul->hari ?? null),
                                        'jam_mulai' => method_exists($matkul, 'primaryStartTime') ? $matkul->primaryStartTime() : ($matkul->jam_mulai ?? null),
                                        'jam_selesai' => method_exists($matkul, 'primaryEndTime') ? $matkul->primaryEndTime() : ($matkul->jam_selesai ?? null),
                                        'ruangan' => method_exists($matkul, 'primaryRoom') ? $matkul->primaryRoom() : ($matkul->ruangan ?? null),
                                        'kelas' => method_exists($matkul, 'primaryClass') ? $matkul->primaryClass() : ($matkul->kelas ?? null),
                                    ]]);
                                }

                                return $entries
                                    ->map(function ($entry) use ($matkul, $formatLegacyTime, $normalizeTimeForSort) {
                                        $dayKey = Str::lower(trim((string) ($entry['hari'] ?? ($matkul->primaryDay() ?? $matkul->hari ?? ''))));
                                        if ($dayKey === '') {
                                            $dayKey = 'hari-belum-ditentukan';
                                        }
                                        $startTime = $entry['jam_mulai']
                                            ?? (method_exists($matkul, 'primaryStartTime')
                                                ? $matkul->primaryStartTime()
                                                : ($matkul->jam_mulai ?? null));
                                        $endTime = $entry['jam_selesai']
                                            ?? (method_exists($matkul, 'primaryEndTime')
                                                ? $matkul->primaryEndTime()
                                            : ($matkul->jam_selesai ?? null));
                                        $timeLabel = $formatLegacyTime($startTime) && $formatLegacyTime($endTime)
                                            ? $formatLegacyTime($startTime) . ' - ' . $formatLegacyTime($endTime)
                                            : ($formatLegacyTime($startTime) ?: $formatLegacyTime($endTime));

                                        return [
                                            'name' => $matkul->nama ?? 'Matkul',
                                            'time' => $timeLabel,
                                            'room' => $entry['ruangan'] ?? ($matkul->primaryRoom() ?? $matkul->ruangan ?? null),
                                            'class' => $entry['kelas'] ?? ($matkul->primaryClass() ?? $matkul->kelas ?? null),
                                            'sort' => $normalizeTimeForSort($formatLegacyTime($startTime)),
                                            'color' => $matkul->warna_label ?? '#4F46E5',
                                            'day_key' => $dayKey,
                                        ];
                                    })
                                    ->filter()
                                    ->values();
                            })
                            ->filter()
                            ->filter(fn ($entry) => ($entry['day_key'] ?? null) === $todayDayKey || ($entry['day_key'] ?? null) === 'hari-belum-ditentukan')
                            ->sortBy('sort')
                            ->values();

                        $badgeColor = match ($agenda->jenis) {
                            'kuliah' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'uts', 'uas' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'libur' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                        };
                    @endphp
                    <article class="mb-4 last:mb-0 rounded-2xl border border-gray-100 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/50">
                        <header class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ ucfirst($agenda->jenis ?? 'Agenda') }}</p>
                                <p class="text-xs text-gray-500 dark:text-slate-400">{{ $rangeLabel }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold {{ $badgeColor }}">
                                Demo
                            </span>
                        </header>

                        @if($scheduleEntries->isNotEmpty())
                            <div class="mt-3 space-y-2">
                                @foreach($scheduleEntries as $entry)
                                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-slate-800 dark:bg-slate-800/70 dark:text-slate-200">
                                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $entry['color'] }};"></span>
                                            {{ $entry['name'] }}
                                        </span>
                                        @if($entry['time'])
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ $entry['time'] }}
                                            </span>
                                        @endif
                                        @if($entry['class'])
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M5 12h14M5 12a5 5 0 010-10h14a5 5 0 010 10M5 12a5 5 0 000 10h14a5 5 0 000-10" />
                                                </svg>
                                                {{ $entry['class'] }}
                                            </span>
                                        @endif
                                        @if($entry['room'])
                                            <span class="inline-flex items-center gap-1">
                                                <svg class="h-3.5 w-3.5 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                          d="M12 21c-4.418 0-8-3.134-8-7s3.582-7 8-7 8 3.134 8 7-3.582 7-8 7z" />
                                                </svg>
                                                {{ $entry['room'] }}
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">Belum ada matkul terhubung.</p>
                        @endif
                    </article>
                @endforeach
            @else
                <p class="text-sm text-gray-500">Belum ada jadwal untuk hari ini.</p>
            @endif
        </section>

        {{-- To-Do --}}
        <section class="bg-white rounded-2xl shadow-sm border p-5 h-full flex flex-col dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Prioritas</p>
                    <h2 class="text-lg font-semibold text-gray-900">To-Do List Demo</h2>
                </div>
                <a href="{{ route('guest.todolist.index') }}" class="text-sm text-indigo-600 hover:underline">Lihat semua</a>
            </header>

            @if(!empty($todosPrioritas) && count($todosPrioritas))
                <ul class="space-y-3">
                    @foreach($todosPrioritas as $todo)
                        <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-gray-100 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/40">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full {{ $todo->status ? 'bg-emerald-500' : 'bg-gray-200' }}"></span>
                                <span class="text-sm font-medium {{ $todo->status ? 'line-through text-gray-400 dark:text-slate-500' : 'text-gray-800 dark:text-slate-100' }}">
                                    {{ $todo->nama_item }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                {{ $todo->status ? 'Selesai ' . ($todo->updated_at?->diffForHumans() ?? '') : 'Belum selesai' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500 dark:text-slate-400">Belum ada to-do prioritas.</p>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        {{-- Keuangan --}}
        <section class="bg-white rounded-2xl shadow-sm border p-6 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Keuangan</p>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Ringkasan Bulan Ini</h2>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Data demo dari file lokal, tanpa database.</p>
                </div>
                <a href="{{ route('guest.keuangan.index') }}" class="text-sm text-indigo-600 hover:underline">Detail keuangan</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-green-100 bg-green-50/80 p-4 dark:border-green-400/20 dark:bg-emerald-500/10">
                    <p class="text-xs font-semibold text-green-600 uppercase dark:text-green-200">Total Pemasukan</p>
                    <p class="mt-2 text-2xl font-bold text-green-800 dark:text-green-100">
                        Rp {{ number_format($ringkasanKeuangan['total_pemasukan'] ?? 0,0,',','.') }}
                    </p>
                </div>
                <div class="rounded-2xl border border-rose-100 bg-rose-50/80 p-4 dark:border-rose-400/20 dark:bg-rose-500/10">
                    <p class="text-xs font-semibold text-rose-600 uppercase dark:text-rose-200">Total Pengeluaran</p>
                    <p class="mt-2 text-2xl font-bold text-rose-800 dark:text-rose-100">
                        Rp {{ number_format($ringkasanKeuangan['total_pengeluaran'] ?? 0,0,',','.') }}
                    </p>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/80 p-4 dark:border-indigo-400/20 dark:bg-indigo-500/10">
                    <p class="text-xs font-semibold text-indigo-600 uppercase dark:text-indigo-200">Saldo</p>
                    <p class="mt-2 text-2xl font-bold text-indigo-800 dark:text-indigo-100">
                        Rp {{ number_format($ringkasanKeuangan['saldo_bulan_ini'] ?? 0,0,',','.') }}
                    </p>
                </div>
            </div>
            <p class="mt-4 text-xs text-gray-500 dark:text-slate-400">Ubah contoh nominal di <code>storage/app/guest/workspace.json</code>.</p>
        </section>

        {{-- Reminders --}}
        <section class="bg-white rounded-2xl shadow-sm border p-5 h-full flex flex-col dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Pengingat</p>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Reminder Mendatang</h2>
                </div>
                <span class="text-xs rounded-full bg-indigo-50 px-3 py-1 font-semibold text-indigo-700">Demo</span>
            </header>

            @if(!empty($remindersMendatang))
                <ul class="divide-y divide-gray-100 dark:divide-slate-800">
                    @foreach($remindersMendatang as $r)
                        <li class="py-3 space-y-1">
                            <p class="font-medium text-gray-800 dark:text-white">{{ $r['title'] ?? 'Reminder' }}</p>
                            <p class="text-sm text-gray-500 dark:text-slate-400">Tenggat: {{ $r['waktu_formatted'] ?? '-' }}</p>
                            <span class="inline-flex items-center gap-2 rounded-lg px-3 py-1 text-xs font-semibold border border-indigo-100 bg-indigo-50 text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-100">
                                {{ $r['time_left_text'] ?? 'Segera' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500 dark:text-slate-400">Belum ada reminder aktif.</p>
            @endif
        </section>
    </div>

    <div class="mt-6 rounded-2xl border border-dashed border-gray-200 bg-white p-5 shadow-sm dark:bg-slate-900 dark:border-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Akses Cepat</p>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cek menu lain di mode tamu</h3>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('guest.keuangan.index') }}" class="px-4 py-2 rounded-xl border border-indigo-100 bg-indigo-50 text-sm font-semibold text-indigo-700 hover:border-indigo-200">Keuangan</a>
                <a href="{{ route('guest.jadwal.index') }}" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300">Jadwal</a>
                <a href="{{ route('guest.todolist.index') }}" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300">To-Do</a>
                <a href="{{ route('guest.catatan.index') }}" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300">Catatan</a>
                <a href="{{ route('guest.ipk.index') }}" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:border-gray-300">IPK</a>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">Semua data di atas hanya dibaca dari file lokal—aman untuk eksplorasi tanpa login.</p>
    </div>
@endsection
