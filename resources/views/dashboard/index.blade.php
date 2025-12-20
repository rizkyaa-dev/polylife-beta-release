{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
    @php
        use Illuminate\Support\Carbon;
        use Illuminate\Support\Str;

        $guestMode = $guestMode ?? false;
        $jadwalIndexRoute = $guestMode ? route('guest.jadwal.index') : route('jadwal.index');
        $todolistIndexRoute = $guestMode ? route('guest.todolist.index') : route('todolist.index');
        $todolistToggleEnabled = ! $guestMode;
        $todolistEditEnabled = ! $guestMode;
        $keuanganFormAction = $guestMode ? route('guest.home') : route('workspace.home');
        $reminderManageRoute = $guestMode ? null : route('reminder.index');
        $reminderDataEndpoint = $guestMode ? null : route('dashboard.reminders.data');
        $keuanganDataEndpoint = $guestMode ? null : route('dashboard.keuangan.data');

        $globalMatkuls = isset($matkuls) ? $matkuls : collect();
        if (!($globalMatkuls instanceof \Illuminate\Support\Collection)) {
            $globalMatkuls = collect($globalMatkuls);
        }

        $hasMatkulDayData = function ($matkul) {
            if (!$matkul) return false;
            if (method_exists($matkul, 'scheduleDays')) {
                return $matkul->scheduleDays()->isNotEmpty();
            }
            $raw = (string) ($matkul->hari ?? '');
            return trim($raw) !== '';
        };
    @endphp
    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Kartu: Jadwal Hari Ini --}}
        <section class="bg-white rounded-2xl shadow-sm border p-5 h-full flex flex-col dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Jadwal Hari Ini</h2>
                <a href="{{ $jadwalIndexRoute }}" class="text-sm text-indigo-600 hover:underline">Lihat semua</a>
            </header>

            @php
                $kegiatanByJadwal = $kegiatanByJadwal ?? collect();
                $jadwalCollection = collect($jadwalHariIni ?? []);
                $todayObject = isset($todayDate) ? Carbon::parse($todayDate) : Carbon::today();
                $todayDayIndex = $todayObject->dayOfWeek;

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

                $dayOrderMap = [
                    'minggu' => 0,
                    'senin' => 1,
                    'selasa' => 2,
                    'rabu' => 3,
                    'kamis' => 4,
                    'jumat' => 5,
                    'jum\'at' => 5,
                    'sabtu' => 6,
                ];

                $dayLabelMap = [
                    'minggu' => 'Minggu',
                    'senin' => 'Senin',
                    'selasa' => 'Selasa',
                    'rabu' => 'Rabu',
                    'kamis' => 'Kamis',
                    'jumat' => 'Jumat',
                    'jum\'at' => "Jum'at",
                    'sabtu' => 'Sabtu',
                ];

                $indexToDayKey = [
                    0 => 'minggu',
                    1 => 'senin',
                    2 => 'selasa',
                    3 => 'rabu',
                    4 => 'kamis',
                    5 => 'jumat',
                    6 => 'sabtu',
                ];

                $todayDayKey = $indexToDayKey[$todayDayIndex] ?? Str::lower($todayObject->translatedFormat('l'));

                $normalizeDayMeta = function (?string $dayName) use ($dayOrderMap, $dayLabelMap) {
                    $normalized = Str::lower(trim((string) $dayName));
                    if ($normalized === '') {
                        return [
                            'label' => 'Hari belum ditentukan',
                            'key' => 'hari-belum-ditentukan',
                            'order' => 999,
                        ];
                    }

                    $order = $dayOrderMap[$normalized] ?? (900 + ord($normalized[0] ?? 'a'));

                    return [
                        'label' => $dayLabelMap[$normalized] ?? Str::title($normalized),
                        'key' => $normalized,
                        'order' => $order,
                    ];
                };
            @endphp

            @if($jadwalCollection->isNotEmpty())
                @php
                    $groupedJadwal = $jadwalCollection->groupBy(function ($item) {
                        $startKey = optional($item->tanggal_mulai)->toDateString();
                        $endKey = optional($item->tanggal_selesai)->toDateString();
                        return implode('|', [
                            $item->jenis ?? 'agenda',
                            $startKey,
                            $endKey,
                        ]);
                    });
                @endphp

                <ul class="divide-y divide-gray-100 dark:divide-slate-800">
                    @foreach($groupedJadwal as $groupItems)
                        @php
                            $representative = $groupItems->first();
                            $startLabel = optional($representative->tanggal_mulai)->translatedFormat('d M Y') ?? '-';
                            $endLabel = optional($representative->tanggal_selesai)->translatedFormat('d M Y');
                            $rangeLabel = $endLabel && $endLabel !== $startLabel
                                ? $startLabel . ' - ' . $endLabel
                                : $startLabel;
                            $jenisLabel = ucfirst($representative->jenis ?? 'Agenda');
                            $semesters = $groupItems->pluck('semester')->filter()->unique()->values();
                        @endphp
                        <li class="py-4 space-y-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <p class="font-medium text-gray-900 dark:text-slate-100">
                                        {{ $jenisLabel }}
                                    </p>
                                    <p class="text-xs text-gray-500 flex flex-wrap items-center gap-2 dark:text-slate-400">
                                        <span>{{ $rangeLabel }}</span>
                                        <span>| {{ $jenisLabel }}</span>
                                        @if($semesters->isNotEmpty())
                                            <span>| Semester {{ $semesters->implode(', ') }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            @php
                            $allMatkuls = $groupItems
                                ->flatMap(fn ($item) => collect($item->matkul_details ?? []))
                                ->unique(fn ($matkul) => $matkul->id ?? ($matkul->nama ?? spl_object_hash($matkul)))
                                ->values();
                            $allKegiatan = $groupItems
                                ->flatMap(fn ($item) => $kegiatanByJadwal->get($item->id) ?? collect())
                                ->values();
                            @endphp

                            <div class="rounded-2xl border border-gray-100 bg-white/80 p-4 space-y-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                                @php
                                    $scheduleEntries = $allMatkuls
                                        ->flatMap(function ($matkul) use ($normalizeDayMeta, $formatLegacyTime, $normalizeTimeForSort, $todayDayKey) {
                                            if (!$matkul) {
                                                return collect();
                                            }

                                            $entries = method_exists($matkul, 'scheduleEntries')
                                                ? collect($matkul->scheduleEntries())
                                                : collect();

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
                                                ->map(function ($entry) use ($matkul, $normalizeDayMeta, $formatLegacyTime, $normalizeTimeForSort) {
                                                    $dayMeta = $normalizeDayMeta($entry['hari'] ?? null);
                                                    $startTimeRaw = $entry['jam_mulai']
                                                        ?? (method_exists($matkul, 'primaryStartTime')
                                                            ? $matkul->primaryStartTime()
                                                            : ($matkul->jam_mulai ?? null));
                                                    $endTimeRaw = $entry['jam_selesai']
                                                        ?? (method_exists($matkul, 'primaryEndTime')
                                                            ? $matkul->primaryEndTime()
                                                            : ($matkul->jam_selesai ?? null));
                                                    $startTime = $formatLegacyTime($startTimeRaw);
                                                    $endTime = $formatLegacyTime($endTimeRaw);
                                                    $timeLabel = $startTime && $endTime ? $startTime . ' - ' . $endTime : ($startTime ?: $endTime);
                                                    $kelasLabel = $entry['kelas']
                                                        ?? (method_exists($matkul, 'primaryClass') ? $matkul->primaryClass() : ($matkul->kelas ?? null));
                                                    $ruanganLabel = $entry['ruangan']
                                                        ?? (method_exists($matkul, 'primaryRoom') ? $matkul->primaryRoom() : ($matkul->ruangan ?? null));

                                                    return [
                                                        'instance' => $matkul,
                                                        'day_label' => $dayMeta['label'],
                                                        'day_key' => $dayMeta['key'],
                                                        'day_order' => $dayMeta['order'],
                                                        'time_label' => $timeLabel,
                                                        'kelas' => $kelasLabel,
                                                        'ruangan' => $ruanganLabel,
                                                        'color' => $matkul->warna_label ?? '#4F46E5',
                                                        'sort_key' => sprintf('%03d-%s', $dayMeta['order'], $normalizeTimeForSort($startTime)),
                                                    ];
                                                })
                                                ->filter(fn ($entry) => $entry['instance']);
                                        })
                                        ->filter()
                                        ->filter(function ($entry) use ($todayDayKey, $hasMatkulDayData) {
                                            $instance = $entry['instance'] ?? null;
                                            if ($entry['day_key'] === $todayDayKey) {
                                                return true;
                                            }
                                            return ! $hasMatkulDayData($instance) && $entry['day_key'] === 'hari-belum-ditentukan';
                                        })
                                        ->values();

                                    $scheduleByDay = $scheduleEntries
                                        ->sortBy('sort_key')
                                        ->groupBy('day_key')
                                        ->map(function ($entries) {
                                            $first = $entries->first();
                                            return [
                                                'label' => $first['day_label'],
                                                'order' => $first['day_order'],
                                                'items' => $entries->values(),
                                            ];
                                        })
                                        ->sortBy('order')
                                        ->values();

                                    $totalScheduleCount = $scheduleByDay->sum(fn ($group) => $group['items']->count());
                                @endphp
                                @if($scheduleByDay->isNotEmpty())
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-slate-300">
                                            <span>Matkul hari ini</span>
                                            <span>{{ $totalScheduleCount }} sesi</span>
                                        </div>
                                        <div class="space-y-3">
                                            @foreach($scheduleByDay as $daySchedule)
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $daySchedule['label'] }}</p>
                                                        <span class="text-xs text-gray-500 dark:text-slate-300">{{ $daySchedule['items']->count() }} matkul</span>
                                                    </div>
                                                    <div class="grid gap-2">
                                                        @foreach($daySchedule['items'] as $matkulDetail)
                                                            @php
                                                                $matkul = $matkulDetail['instance'];
                                                                $timeLabel = $matkulDetail['time_label'];
                                                                $kelasLabel = $matkulDetail['kelas'];
                                                                $ruanganLabel = $matkulDetail['ruangan'];
                                                                $chipColor = $matkulDetail['color'];
                                                            @endphp
                                                            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 bg-gray-50/80 px-3 py-2 text-[11px] dark:border-slate-700 dark:bg-slate-800/60">
                                                                <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-slate-50">
                                                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $chipColor }};"></span>
                                                                    {{ $matkul->nama ?? 'Matkul' }}
                                                                </span>
                                                                @if($timeLabel)
                                                                    <span class="inline-flex items-center gap-1 text-gray-600 dark:text-slate-300">
                                                                        <svg class="h-3.5 w-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                                  d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                        </svg>
                                                                        {{ $timeLabel }}
                                                                    </span>
                                                                @endif
                                                                @if($kelasLabel)
                                                                    <span class="inline-flex items-center gap-1 text-gray-600 dark:text-slate-300">
                                                                        <svg class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                                  d="M5 12h14M5 12a5 5 0 010-10h14a5 5 0 010 10M5 12a5 5 0 000 10h14a5 5 0 000-10" />
                                                                        </svg>
                                                                        {{ $kelasLabel }}
                                                                    </span>
                                                                @endif
                                                                @if($ruanganLabel)
                                                                    <span class="inline-flex items-center gap-1 text-gray-600 dark:text-slate-300">
                                                                        <svg class="h-3.5 w-3.5 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                                  d="M12 21c-4.418 0-8-3.134-8-7s3.582-7 8-7 8 3.134 8 7-3.582 7-8 7z" />
                                                                        </svg>
                                                                        {{ $ruanganLabel }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <p class="text-xs text-gray-500 dark:text-slate-400">Belum ada matkul yang dihubungkan untuk agenda ini.</p>
                                @endif

                                @if($allKegiatan->isNotEmpty())
                                    <div class="space-y-2">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-slate-300">Kegiatan terkait</p>
                                        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/70 p-3 space-y-1 text-xs text-gray-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                            @foreach($allKegiatan as $kegiatan)
                                                @php
                                                    $deadlineDate = $kegiatan->tanggal_deadline
                                                        ? \Illuminate\Support\Carbon::parse($kegiatan->tanggal_deadline)->translatedFormat('d M Y')
                                                        : 'Tanggal belum ditentukan';
                                                    $timeLabel = $kegiatan->waktu
                                                        ? \Illuminate\Support\Carbon::parse($kegiatan->waktu)->format('H:i')
                                                        : null;
                                                @endphp
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <span class="font-semibold text-gray-800 dark:text-slate-100">{{ $kegiatan->nama_kegiatan }}</span>
                                                    <span class="text-gray-500 dark:text-slate-400">
                                                        • {{ $deadlineDate }}
                                                        @if($timeLabel)
                                                            • {{ $timeLabel }}
                                                        @endif
                                                        @if($kegiatan->lokasi)
                                                            • {{ $kegiatan->lokasi }}
                                                        @endif
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">Belum ada jadwal untuk hari ini.</p>
            @endif

        </section>
        
        {{-- Kartu: To-Do Prioritas --}}
        <section class="bg-white rounded-2xl shadow-sm border p-5 h-full flex flex-col dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">To-Do List Prioritas</h2>
                <a href="{{ $todolistIndexRoute }}" class="text-sm text-indigo-600 hover:underline">Kelola</a>
            </header>

            @if(!empty($todosPrioritas) && count($todosPrioritas))
                <ul class="space-y-3">
                    @foreach($todosPrioritas as $todo)
                        @php
                            $secondsLeft = $todo->status
                                ? max(0, 600 - now()->diffInSeconds($todo->updated_at ?? now()))
                                : null;
                            $minutesLeft = $secondsLeft ? (int) ceil($secondsLeft / 60) : null;
                        @endphp
                        <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-gray-100 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/40"
                            data-todo-card
                            data-todo-id="{{ $todo->id }}"
                            @if($secondsLeft) data-remove-after="{{ $secondsLeft }}" @endif>
                            <div class="flex items-center gap-3">
                                @if($todolistToggleEnabled)
                                    <input type="checkbox"
                                           data-todo-toggle
                                           data-toggle-url="{{ route('todolist.toggle-status', $todo) }}"
                                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-600 dark:text-indigo-300 dark:focus:ring-indigo-300"
                                           {{ $todo->status ? 'checked' : '' }}>
                                @else
                                    <span class="h-4 w-4 rounded border border-gray-200 bg-gray-100 dark:border-slate-700 dark:bg-slate-800"></span>
                                @endif
                                <span data-todo-text class="text-sm font-medium {{ $todo->status ? 'line-through text-gray-400 dark:text-slate-500' : 'text-gray-800 dark:text-slate-100' }}">
                                    {{ $todo->nama_item }}
                                </span>
                            </div>
                            <div class="flex flex-col gap-1 text-right">
                                @if($todolistEditEnabled)
                                    <a href="{{ route('todolist.edit', $todo->id) }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                                @endif
                                <p class="text-xs text-gray-500 dark:text-slate-400" data-todo-meta>
                                    @if($todo->status)
                                        Ditandai selesai {{ $todo->updated_at?->diffForHumans() }} • hilang dalam {{ $minutesLeft }} menit
                                    @else
                                        Centang untuk menandai selesai.
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500 dark:text-slate-400">Belum ada to-do prioritas.</p>
            @endif
        </section>

        {{-- Kartu: Keuangan Bulan Ini --}}
        <section class="bg-white rounded-2xl shadow-sm border p-6 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Keuangan Bulan Ini</h2>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Pilih periode untuk melihat ringkasan pemasukan, pengeluaran, dan saldo.</p>
                </div>
                <form method="GET" action="{{ $keuanganFormAction }}" class="flex items-center gap-2 text-sm">
                    @foreach(request()->except('bulan') as $key => $value)
                        @if(is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="bulanKeuanganSelect" class="text-gray-600 dark:text-slate-300">Bulan</label>
                    <div class="relative">
                        <select id="bulanKeuanganSelect"
                                name="bulan"
                                onchange="this.form.submit()"
                                class="appearance-none rounded-xl border border-indigo-100 bg-white/80 pr-10 pl-3 py-2 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/60 dark:bg-slate-900/70 dark:border-slate-700 dark:text-slate-100 dark:focus:border-indigo-300 dark:focus:ring-indigo-300/60">
                            @foreach($bulanOptions ?? [] as $option)
                                <option value="{{ $option['value'] }}" {{ ($option['value'] ?? '') === ($bulanDipilih ?? now()->format('Y-m')) ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                    </div>
                </form>
            </div>
            <div class="flex flex-col items-center gap-6 mb-6">
                <div class="relative w-full max-w-xs sm:max-w-sm aspect-square bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 rounded-full p-6 sm:p-8 shadow-inner dark:from-slate-800 dark:via-slate-900 dark:to-slate-950">
                    <canvas id="chartKeuanganPie" class="transition-opacity duration-300" style="position: relative; z-index: 2;"></canvas>
                    <div class="absolute inset-[14%] sm:inset-10 flex items-center justify-center pointer-events-none" style="z-index: 4;">
                        <div id="saldoWindow" class="saldo-window relative w-full h-full max-w-[140px] max-h-[140px] rounded-full bg-white shadow-lg overflow-hidden ring-2 ring-indigo-100 dark:bg-slate-950 dark:ring-indigo-900/50">
                            <div id="saldoLiquid" class="saldo-liquid absolute inset-x-0 bottom-0 h-1/2"></div>
                            <div class="saldo-wave saldo-wave-one"></div>
                            <div class="saldo-wave saldo-wave-two"></div>
                            <div class="absolute inset-0 flex items-center justify-center text-center">
                                <p id="saldoIndicatorValue" class="text-2xl font-semibold text-indigo-800 dark:text-indigo-200">0%</p>
                            </div>
                        </div>
                    </div>
                    <div id="chartLoading" class="absolute inset-6 flex items-center justify-center bg-white bg-opacity-90 rounded-full dark:bg-slate-900/90">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                </div>
            </div>

            @php
                $saldoNegatif = ($ringkasanKeuangan['saldo_bulan_ini'] ?? 0) < 0;
            @endphp
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 w-full">
                <button type="button"
                        data-slice="pemasukan"
                        class="stat-card rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow focus-visible:ring-2 focus-visible:ring-green-400 w-full ring-green-300 dark:ring-green-400 ring-offset-white dark:ring-offset-slate-900">
                    <div class="flex items-center gap-2 text-sm font-medium text-green-700">
                        <span class="inline-flex h-3 w-3 rounded-full" style="background-color: rgba(34, 197, 94, 0.9);"></span>
                        Pemasukan
                    </div>
                    <p id="statPemasukan" data-raw="{{ $ringkasanKeuangan['total_pemasukan'] ?? 0 }}" class="text-2xl font-semibold text-green-800 transition">
                        {{ isset($ringkasanKeuangan['total_pemasukan']) ? 'Rp '.number_format($ringkasanKeuangan['total_pemasukan'],0,',','.') : 'Rp 0' }}
                    </p>
                    <p class="text-xs text-green-700/70 mt-1">Total dana masuk bulan ini</p>
                </button>
                <button type="button"
                        data-slice="pengeluaran"
                        class="stat-card rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow focus-visible:ring-2 focus-visible:ring-rose-400 w-full ring-rose-300 dark:ring-rose-400 ring-offset-white dark:ring-offset-slate-900">
                    <div class="flex items-center gap-2 text-sm font-medium text-rose-700">
                        <span class="inline-flex h-3 w-3 rounded-full" style="background-color: rgba(239, 68, 68, 0.9);"></span>
                        Pengeluaran
                    </div>
                    <p id="statPengeluaran" data-raw="{{ $ringkasanKeuangan['total_pengeluaran'] ?? 0 }}" class="text-2xl font-semibold text-rose-800 transition">
                        {{ isset($ringkasanKeuangan['total_pengeluaran']) ? 'Rp '.number_format($ringkasanKeuangan['total_pengeluaran'],0,',','.') : 'Rp 0' }}
                    </p>
                    <p class="text-xs text-rose-700/70 mt-1">Total dana keluar bulan ini</p>
                </button>
                <button type="button"
                        data-slice="saldo"
                        id="saldoCard"
                        class="stat-card rounded-2xl border px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow focus-visible:ring-2 w-full ring-offset-white dark:ring-offset-slate-900 {{ $saldoNegatif ? 'border-gray-900 bg-gray-900 text-white focus-visible:ring-gray-700 ring-gray-700 dark:ring-gray-500' : 'border-indigo-100 bg-indigo-50 text-indigo-800 focus-visible:ring-indigo-400 ring-indigo-200 dark:ring-indigo-500' }}">
                    <div id="saldoLabelWrap" class="flex items-center gap-2 text-sm font-medium {{ $saldoNegatif ? 'text-white' : 'text-indigo-700' }}">
                        <span class="inline-flex h-3 w-3 rounded-full" style="background-color: rgba(99, 102, 241, 0.9);"></span>
                        <span id="labelSaldo">{{ ($ringkasanKeuangan['saldo_bulan_ini'] ?? 0) < 0 ? 'Hutang' : 'Saldo' }}</span>
                    </div>
                    <p id="statSaldo" data-raw="{{ $ringkasanKeuangan['saldo_bulan_ini'] ?? 0 }}" class="text-2xl font-semibold transition {{ $saldoNegatif ? 'text-white' : 'text-indigo-800' }}">
                        {{ isset($ringkasanKeuangan['saldo_bulan_ini']) ? 'Rp '.number_format($ringkasanKeuangan['saldo_bulan_ini'],0,',','.') : 'Rp 0' }}
                    </p>
                    <p class="text-xs mt-1 {{ $saldoNegatif ? 'text-gray-200' : 'text-indigo-700/70' }}" id="saldoSubtitle">
                        {{ ($ringkasanKeuangan['saldo_bulan_ini'] ?? 0) < 0 ? 'Total hutang per '.now()->format('d M') : 'Sisa dana per '.now()->format('d M') }}
                    </p>
                </button>
            </div>
        </section>

        {{-- Kartu: Reminder Mendatang --}}
        <section class="bg-white rounded-2xl shadow-sm border p-5 h-full flex flex-col dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Reminder Mendatang</h2>
                @if($reminderManageRoute)
                    <a href="{{ $reminderManageRoute }}" class="text-sm text-indigo-600 hover:underline">Kelola</a>
                @endif
            </header>

            @php
                $hasReminders = isset($remindersMendatang) && count($remindersMendatang);
            @endphp
            @php
                $hasReminders = isset($remindersMendatang) && count($remindersMendatang);
            @endphp
            <ul class="divide-y divide-gray-100 dark:divide-slate-800" data-reminder-list>
                @forelse($remindersMendatang as $r)
                    <li class="py-3 space-y-2"
                        data-reminder-item
                        data-reminder-id="{{ $r['id'] }}"
                        data-seconds-left="{{ $r['seconds_left'] }}"
                        data-reminder-title="{{ $r['title'] }}"
                        data-reminder-deadline="{{ $r['waktu_formatted'] }}">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-gray-800">{{ $r['title'] }}</p>
                                <p class="text-sm text-gray-500">Tenggat: {{ $r['waktu_formatted'] }}</p>
                            </div>
                            <a href="{{ $r['edit_url'] }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-2 rounded-lg px-3 py-1 text-xs font-semibold border {{ $r['badge_classes'] }} {{ !empty($r['blink']) ? 'reminder-blink' : '' }}"
                                  data-reminder-badge>
                                <span class="reminder-dot h-2.5 w-2.5 rounded-full {{ $r['dot_classes'] }}"
                                      data-reminder-dot></span>
                                <span data-reminder-timeleft>{{ $r['time_left_text'] }}</span>
                            </span>
                        </div>
                    </li>
                @empty
                @endforelse
            </ul>
            <p class="text-sm text-gray-500 dark:text-slate-400 {{ $hasReminders ? 'hidden' : '' }}" id="reminderEmptyState">
                Belum ada reminder aktif.
            </p>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .saldo-window {
            box-shadow: inset 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .saldo-liquid {
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.9) 0%, rgba(59, 130, 246, 0.8) 70%);
            transition: height 0.8s ease, background 0.4s ease;
        }
        .saldo-wave {
            position: absolute;
            left: -25%;
            width: 150%;
            height: 60%;
            background: rgba(255, 255, 255, 0.2);
            opacity: 0.6;
            filter: blur(6px);
            animation: waveMotion 6s linear infinite;
        }
        .saldo-wave-one {
            bottom: 10%;
        }
        .saldo-wave-two {
            bottom: 0;
            animation-duration: 8s;
            animation-direction: reverse;
            opacity: 0.4;
        }
        @keyframes waveMotion {
            0% { transform: translateX(0) rotate(0deg); }
            50% { transform: translateX(10%) rotate(2deg); }
            100% { transform: translateX(0) rotate(0deg); }
        }
        .chart-tooltip {
            position: absolute;
            pointer-events: none;
            z-index: 999;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .reminder-blink {
            animation: reminderBlink 0.9s linear infinite;
        }
        .reminder-blink .reminder-dot {
            animation: reminderDotBlink 0.9s linear infinite;
        }
        @keyframes reminderBlink {
            0%, 100% {
                background-color: #7f1d1d;
                color: #fff;
                border-color: #000;
            }
            50% {
                background-color: #000;
                color: #fca5a5;
                border-color: #7f1d1d;
            }
        }
        @keyframes reminderDotBlink {
            0%, 100% { background-color: #fecaca; }
            50% { background-color: #000; }
        }
    </style>
@endpush

@include('dashboard.partials.reminder-notifications')

@push('scripts')
    {{-- Pie Chart Keuangan yang otomatis update --}}
    <script>
        const initDashboardInteractive = () => {
            const isGuestMode = document.body?.dataset?.guestMode === '1';
            if (isGuestMode) {
                return;
            }
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const todoRemovalTimers = new Map();
            const reminderCountdowns = new Map();

            const escapeHtml = (value = '') => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const cancelRemoval = (card) => {
                if (!card) return;
                const key = card.dataset.todoId;
                if (!key) return;
                if (todoRemovalTimers.has(key)) {
                    clearTimeout(todoRemovalTimers.get(key));
                    todoRemovalTimers.delete(key);
                }
            };

            const scheduleRemoval = (card, seconds) => {
                if (!card) return;
                const key = card.dataset.todoId;
                if (!key || !seconds || seconds <= 0) return;
                cancelRemoval(card);
                const timerId = setTimeout(() => {
                    card.classList.add('opacity-0', 'pointer-events-none');
                    setTimeout(() => card.remove(), 300);
                }, seconds * 1000);
                todoRemovalTimers.set(key, timerId);
            };

            document.querySelectorAll('[data-todo-card]').forEach((card) => {
                const removeAfter = Number(card.dataset.removeAfter ?? 0);
                if (removeAfter > 0) {
                    scheduleRemoval(card, removeAfter);
                }
            });

            const toggleTodo = async (checkbox) => {
                const url = checkbox.dataset.toggleUrl;
                const card = checkbox.closest('[data-todo-card]');
                const textEl = card?.querySelector('[data-todo-text]');
                const metaEl = card?.querySelector('[data-todo-meta]');
                const isChecked = checkbox.checked;

                if (!url || !csrfToken) {
                    checkbox.checked = !isChecked;
                    return;
                }

                checkbox.disabled = true;
                card?.classList.add('opacity-70');

                try {
                    const response = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ status: isChecked ? 1 : 0 }),
                    });
                    if (!response.ok) {
                        throw new Error('Gagal memperbarui status to-do.');
                    }
                    const payload = await response.json();

                    textEl?.classList.toggle('line-through', payload.status);
                    textEl?.classList.toggle('text-gray-400', payload.status);
                    textEl?.classList.toggle('text-gray-800', !payload.status);

                    if (metaEl) {
                        metaEl.textContent = payload.meta ?? (payload.status
                            ? 'Ditandai selesai - akan hilang dalam 10 menit.'
                            : 'Centang untuk menandai selesai.');
                    }

                    if (card) {
                        if (payload.status) {
                            const seconds = Number(payload.visible_for_seconds ?? 600);
                            card.dataset.removeAfter = String(seconds);
                            card.classList.remove('opacity-0', 'pointer-events-none');
                            scheduleRemoval(card, seconds);
                        } else {
                            card.dataset.removeAfter = '';
                            cancelRemoval(card);
                            card.classList.remove('opacity-0', 'pointer-events-none');
                        }
                    }
                } catch (error) {
                    checkbox.checked = !isChecked;
                    alert(error.message ?? 'Terjadi kesalahan saat memperbarui to-do.');
                } finally {
                    checkbox.disabled = false;
                    card?.classList.remove('opacity-70');
                }
            };

            document.querySelectorAll('[data-todo-toggle]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => toggleTodo(checkbox));
            });

            const reminderListEl = document.querySelector('[data-reminder-list]');
            const reminderEmptyEl = document.getElementById('reminderEmptyState');
            const reminderEndpoint = '{{ $reminderDataEndpoint ?? '' }}';
            let reminderInterval = null;
            let reminderTickInterval = null;
            const REMINDER_BADGE_BASE = 'inline-flex items-center gap-2 rounded-lg px-3 py-1 text-xs font-semibold border';
            const REMINDER_DOT_BASE = 'reminder-dot h-2.5 w-2.5 rounded-full';
            const reminderNotifier = window.buildReminderNotifier
                ? window.buildReminderNotifier()
                : null;

            const resetReminderCountdowns = () => {
                reminderCountdowns.clear();
            };

            const formatTimeLeftText = (seconds) => {
                if (seconds <= 0) return 'Segera jatuh tempo';

                const minute = 60;
                const hour = 60 * minute;
                const day = 24 * hour;
                const week = 7 * day;

                const weeks = Math.floor(seconds / week);
                const days = Math.floor(seconds / day) % 7;
                const hours = Math.floor(seconds / hour) % 24;
                const minutes = Math.floor(seconds / minute) % 60;

                const parts = [];
                if (weeks > 0) parts.push(`${weeks} minggu`);
                if (days > 0 && parts.length < 2) parts.push(`${days} hari`);
                if (weeks === 0 && hours > 0 && parts.length < 2) parts.push(`${hours} jam`);
                if (weeks === 0 && days === 0 && minutes > 0 && parts.length < 2) parts.push(`${minutes} menit`);
                if (!parts.length) parts.push('kurang dari 1 menit');

                return `Sisa ${parts.join(' ')}`;
            };

            const getReminderUrgencyState = (seconds) => {
                const oneDay = 24 * 3600;
                const oneWeek = 7 * oneDay;
                const threeHours = 3 * 3600;

                if (seconds >= oneWeek) {
                    return {
                        badge: 'bg-green-50 text-green-700 border-green-100',
                        dot: 'bg-green-400',
                        blink: false,
                    };
                }

                if (seconds >= oneDay) {
                    return {
                        badge: 'bg-amber-50 text-amber-700 border-amber-200',
                        dot: 'bg-amber-400',
                        blink: false,
                    };
                }

                if (seconds >= threeHours) {
                    return {
                        badge: 'bg-rose-50 text-rose-700 border-rose-200',
                        dot: 'bg-rose-400',
                        blink: false,
                    };
                }

                return {
                    badge: 'bg-rose-700 text-white border-black dark:border-white',
                    dot: 'reminder-dot-critical',
                    blink: true,
                };
            };

            const applyReminderVisual = (entry) => {
                const { badgeEl, dotEl, timeTextEl } = entry;
                const seconds = entry.seconds;
                const state = getReminderUrgencyState(seconds);
                if (badgeEl) {
                    badgeEl.className = `${REMINDER_BADGE_BASE} ${state.badge}`;
                    badgeEl.classList.toggle('reminder-blink', state.blink);
                }
                if (dotEl) {
                    dotEl.className = `${REMINDER_DOT_BASE} ${state.dot}`;
                }
                if (timeTextEl) {
                    timeTextEl.textContent = formatTimeLeftText(seconds);
                }
            };

            const updateReminderCountdowns = () => {
                reminderCountdowns.forEach((entry) => {
                    const previousSeconds = entry.seconds;
                    entry.seconds = Math.max(0, entry.seconds - 1);
                    if (reminderNotifier) {
                        reminderNotifier.handleCountdown(entry, previousSeconds);
                    }
                    applyReminderVisual(entry);
                });
            };

            const ensureReminderTick = () => {
                if (!reminderTickInterval) {
                    reminderTickInterval = setInterval(updateReminderCountdowns, 1000);
                }
            };

            const registerReminderItems = () => {
                resetReminderCountdowns();
                let hasItems = false;
                document.querySelectorAll('[data-reminder-item]').forEach((itemEl) => {
                    const id = itemEl.dataset.reminderId;
                    if (!id) return;
                    hasItems = true;
                    const seconds = Number(itemEl.dataset.secondsLeft ?? 0);
                    const badgeEl = itemEl.querySelector('[data-reminder-badge]');
                    const dotEl = itemEl.querySelector('[data-reminder-dot]');
                    const timeTextEl = itemEl.querySelector('[data-reminder-timeleft]');
                    const title = itemEl.dataset.reminderTitle || 'Reminder';
                    const deadlineText = itemEl.dataset.reminderDeadline || '';
                    const entry = {
                        id,
                        itemEl,
                        badgeEl,
                        dotEl,
                        timeTextEl,
                        seconds: Number.isFinite(seconds) ? seconds : 0,
                        title,
                        deadlineText,
                        notified: new Set(),
                    };
                    reminderCountdowns.set(id, entry);
                    if (reminderNotifier) {
                        reminderNotifier.attachEntry(entry);
                        reminderNotifier.handleCountdown(entry, entry.seconds + 1);
                    }
                    applyReminderVisual(entry);
                });
                if (reminderNotifier) {
                    reminderNotifier.requestPermissionIfNeeded(hasItems);
                }
                ensureReminderTick();
            };

            const renderReminders = (items = []) => {
                if (!reminderListEl) return;
                if (!items.length) {
                    reminderListEl.innerHTML = '';
                    reminderEmptyEl?.classList.remove('hidden');
                    resetReminderCountdowns();
                    reminderNotifier?.clear();
                    return;
                }

                const html = items.map((item) => {
                    const badgeClasses = `${item.badge_classes ?? ''} ${item.blink ? 'reminder-blink' : ''}`;
                    const dotClasses = `reminder-dot h-2.5 w-2.5 rounded-full ${item.dot_classes ?? ''}`;
                    const timeLeftText = escapeHtml(item.time_left_text ?? 'Sisa waktu tidak diketahui');
                    const waktuFormatted = escapeHtml(item.waktu_formatted ?? '');
                    const timeDiff = escapeHtml(item.time_diff ?? '');
                    const title = escapeHtml(item.title ?? 'Reminder');
                    const editUrl = escapeHtml(item.edit_url ?? '#');

                    return `
                        <li class="py-3 space-y-2"
                            data-reminder-item
                            data-reminder-id="${escapeHtml(item.id ?? '')}"
                            data-seconds-left="${Number(item.seconds_left ?? 0)}"
                            data-reminder-title="${title}"
                            data-reminder-deadline="${waktuFormatted}">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-gray-800">${title}</p>
                                    <p class="text-sm text-gray-500">Tenggat: ${waktuFormatted}</p>
                                </div>
                                <a href="${editUrl}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="${REMINDER_BADGE_BASE} ${badgeClasses}" data-reminder-badge>
                                    <span class="${REMINDER_DOT_BASE} ${dotClasses}" data-reminder-dot></span>
                                    <span data-reminder-timeleft>${timeLeftText}</span>
                                </span>
                            </div>
                        </li>
                    `;
                }).join('');

                reminderListEl.innerHTML = html;
                reminderEmptyEl?.classList.add('hidden');
                registerReminderItems();
            };

            const fetchReminders = async () => {
                if (!reminderListEl) return;
                try {
                    const response = await fetch(reminderEndpoint, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (!response.ok) throw new Error('Gagal memperbarui reminder.');
                    const payload = await response.json();
                    renderReminders(payload.data ?? []);
                } catch (error) {
                    console.warn(error.message ?? error);
                }
            };

            const startReminderInterval = () => {
                if (reminderInterval || !reminderListEl) return;
                reminderInterval = setInterval(fetchReminders, 30000);
            };

            const stopReminderInterval = () => {
                if (!reminderInterval) return;
                clearInterval(reminderInterval);
                reminderInterval = null;
            };

            if (reminderListEl) {
                registerReminderItems();
                fetchReminders();
                startReminderInterval();
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        stopReminderInterval();
                    } else {
                        fetchReminders();
                        startReminderInterval();
                    }
                });
            }

            const ctx = document.getElementById('chartKeuanganPie');
            if (!ctx) return;

            const chartLoading = document.getElementById('chartLoading');
            const statButtons = document.querySelectorAll('.stat-card');
            const bulanSelector = document.getElementById('bulanKeuanganSelect');
            const selectedBulan = bulanSelector?.value ?? '';
            const statEls = {
                pemasukan: document.getElementById('statPemasukan'),
                pengeluaran: document.getElementById('statPengeluaran'),
                saldo: document.getElementById('statSaldo'),
            };

            const formatRupiah = (angka) => 'Rp ' + new Intl.NumberFormat('id-ID').format(angka);

            const updateStatText = (el, value) => {
                if (!el) return;
                el.dataset.raw = value;
                el.textContent = formatRupiah(value);
            };

            const saldoLiquid = document.getElementById('saldoLiquid');
            const saldoWindow = document.getElementById('saldoWindow');
            const saldoText = document.getElementById('saldoIndicatorValue');
            const saldoCard = document.getElementById('saldoCard');
            const saldoLabelWrap = document.getElementById('saldoLabelWrap');
            const saldoSubtitle = document.getElementById('saldoSubtitle');
            const labelSaldo = document.getElementById('labelSaldo');

            const updateSaldoIndicator = (saldoValue, baseline) => {
                if (!saldoLiquid || !saldoWindow || !saldoText) return;

                const rawReference = Math.max(Math.abs(baseline), Math.abs(saldoValue));
                const rawRatio = rawReference === 0 ? 0 : Math.abs(saldoValue) / rawReference;
                // Keep a minimal wave for non-zero values, but allow a true 0% indicator.
                const displayRatio = rawRatio === 0 ? 0 : Math.max(0.05, rawRatio);
                saldoLiquid.style.height = `${displayRatio * 100}%`;

                const positiveGradient = 'linear-gradient(180deg, rgba(129,140,248,0.95) 0%, rgba(59,130,246,0.85) 80%)';
                const negativeGradient = 'linear-gradient(180deg, rgba(15,23,42,0.95) 0%, rgba(0,0,0,0.9) 80%)';
                const isPositive = saldoValue >= 0;

                saldoLiquid.style.background = isPositive ? positiveGradient : negativeGradient;
                saldoWindow.classList.toggle('ring-4', !isPositive);
                saldoWindow.classList.toggle('ring-rose-200', !isPositive);
                saldoWindow.classList.toggle('ring-indigo-200', isPositive);
                saldoText.classList.toggle('text-indigo-800', isPositive);
                saldoText.classList.toggle('text-red-500', !isPositive);
                const percent = Math.round(rawRatio * 100) * (saldoValue >= 0 ? 1 : -1);
                saldoText.textContent = `${percent}%`;
            };

            const applySaldoVisualState = (isNegative) => {
                if (saldoCard) {
                    saldoCard.classList.toggle('bg-gray-900', isNegative);
                    saldoCard.classList.toggle('border-gray-900', isNegative);
                    saldoCard.classList.toggle('text-white', isNegative);
                    saldoCard.classList.toggle('text-indigo-800', !isNegative);
                    saldoCard.classList.toggle('bg-indigo-50', !isNegative);
                    saldoCard.classList.toggle('border-indigo-100', !isNegative);
                    saldoCard.classList.toggle('focus-visible:ring-gray-700', isNegative);
                    saldoCard.classList.toggle('focus-visible:ring-indigo-400', !isNegative);
                }
                saldoLabelWrap?.classList.toggle('text-white', isNegative);
                saldoLabelWrap?.classList.toggle('text-indigo-700', !isNegative);
                statEls.saldo?.classList.toggle('text-white', isNegative);
                statEls.saldo?.classList.toggle('text-indigo-800', !isNegative);
                saldoSubtitle?.classList.toggle('text-gray-200', isNegative);
                saldoSubtitle?.classList.toggle('text-indigo-700/70', !isNegative);
            };

            const loadChartJs = () => {
                return new Promise((resolve, reject) => {
                    if (window.Chart) {
                        resolve();
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                    script.onload = resolve;
                    script.onerror = reject;
                    document.body.appendChild(script);
                });
            };

            const sliceIndexMap = {
                pemasukan: 0,
                pengeluaran: 1,
            };

            let pieChart = null;
            let saldoIndicatorHidden = false;
            let updateInterval = null;

            const baseEndpoint = '{{ $keuanganDataEndpoint ?? '' }}';
            const endpoint = selectedBulan ? `${baseEndpoint}?bulan=${encodeURIComponent(selectedBulan)}` : baseEndpoint;

            let latestData = {
                pemasukan: Number(statEls.pemasukan?.dataset.raw ?? 0),
                pengeluaran: Number(statEls.pengeluaran?.dataset.raw ?? 0),
                saldo: Number(statEls.saldo?.dataset.raw ?? 0),
            };

            const updateSliceVisibility = () => {
                statButtons.forEach((btn) => {
                    if (btn.dataset.slice === 'saldo') {
                        const isHidden = saldoIndicatorHidden;
                        btn.classList.toggle('opacity-60', isHidden);
                        btn.classList.toggle('ring-2', !isHidden);
                        btn.classList.toggle('ring-offset-2', !isHidden);
                        saldoWindow?.classList.toggle('opacity-40', isHidden);
                        saldoText?.classList.toggle('opacity-50', isHidden);
                        saldoLiquid?.classList.toggle('opacity-50', isHidden);
                        return;
                    }

                    const slice = btn.dataset.slice;
                    const index = sliceIndexMap[slice];
                    const isHidden = pieChart
                        ? (pieChart.getDatasetMeta(0).data[index]?.hidden ?? false)
                        : false;
                    btn.classList.toggle('opacity-60', isHidden);
                    btn.classList.toggle('ring-2', !isHidden);
                    btn.classList.toggle('ring-offset-2', !isHidden);
                });
            };

            const toggleSlice = (sliceKey) => {
                if (sliceKey === 'saldo') {
                    saldoIndicatorHidden = !saldoIndicatorHidden;
                    updateSliceVisibility();
                    return;
                }

                if (!pieChart) {
                    return;
                }
                const index = sliceIndexMap[sliceKey];
                if (typeof index === 'undefined') {
                    return;
                }
                const meta = pieChart.getDatasetMeta(0).data[index];
                if (!meta) {
                    return;
                }
                meta.hidden = !meta.hidden;
                pieChart.update();
                updateSliceVisibility();
            };

            const applyFinancialDataToUi = () => {
                updateStatText(statEls.pemasukan, latestData.pemasukan);
                updateStatText(statEls.pengeluaran, latestData.pengeluaran);
                updateStatText(statEls.saldo, latestData.saldo);
                updateSaldoIndicator(latestData.saldo, latestData.pemasukan);

                const isNegative = latestData.saldo < 0;
                if (labelSaldo && saldoSubtitle) {
                    labelSaldo.textContent = isNegative ? 'Hutang' : 'Saldo';
                    saldoSubtitle.textContent = `${isNegative ? 'Total hutang' : 'Sisa dana'} per {{ now()->format('d M') }}`;
                }
                applySaldoVisualState(isNegative);
            };

            const applyFinancialDataToChart = () => {
                if (!pieChart) {
                    return;
                }
                pieChart.data.datasets[0].data = [latestData.pemasukan, latestData.pengeluaran];
                pieChart.update('none');
            };

            const fetchAndUpdate = async () => {
                try {
                    const response = await fetch(endpoint, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (!response.ok) throw new Error('Gagal mengambil data terbaru');
                    const payload = await response.json();
                    const data = payload?.data ?? {};

                    latestData = {
                        pemasukan: Number(data.total_pemasukan ?? 0),
                        pengeluaran: Number(data.total_pengeluaran ?? 0),
                        saldo: Number(data.saldo_bulan_ini ?? 0),
                    };

                    applyFinancialDataToUi();
                    applyFinancialDataToChart();
                    updateSliceVisibility();
                } catch (error) {
                    console.warn(error.message ?? error);
                }
            };

            const startInterval = () => {
                if (updateInterval) return;
                updateInterval = setInterval(fetchAndUpdate, 30000);
            };

            const clearIntervalIfNeeded = () => {
                if (!updateInterval) return;
                clearInterval(updateInterval);
                updateInterval = null;
            };

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    clearIntervalIfNeeded();
                } else {
                    fetchAndUpdate();
                    startInterval();
                }
            });

            statButtons.forEach((btn) => {
                btn.addEventListener('click', () => toggleSlice(btn.dataset.slice));
            });

            const initializeIndicators = () => {
                applyFinancialDataToUi();
                updateSliceVisibility();
            };

            initializeIndicators();
            fetchAndUpdate();
            startInterval();

            loadChartJs()
                .then(() => {
                    chartLoading?.classList.add('hidden');
                    pieChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['Pemasukan', 'Pengeluaran'],
                            datasets: [{
                                data: [
                                    Number(statEls.pemasukan?.dataset.raw ?? 0),
                                    Number(statEls.pengeluaran?.dataset.raw ?? 0),
                                ],
                                backgroundColor: [
                                    'rgba(34, 197, 94, 0.8)',
                                    'rgba(239, 68, 68, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(34, 197, 94, 1)',
                                    'rgba(239, 68, 68, 1)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: { enabled: false },
                            }
                        }
                    });
                    applyFinancialDataToChart();
                    updateSliceVisibility();
                })
                .catch((error) => {
                    console.error('Gagal memuat Chart.js:', error);
                    chartLoading?.classList.add('hidden');
                });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashboardInteractive);
        } else {
            initDashboardInteractive();
        }
    </script>
@endpush
