@extends('layouts.app')

@section('page_title', 'Jadwal Kuliah')

@section('content')
    <style>
        .calendar-kegiatan-dot {
            background-color: #0f172a;
        }
        [data-theme='dark'] .calendar-kegiatan-dot {
            background-color: #ffffff;
            box-shadow: 0 0 0 1px rgba(148, 163, 184, 0.5);
        }
    </style>
    @php
        use Illuminate\Support\Carbon;
        use Illuminate\Support\Str;

        $guestMode = $guestMode ?? false;
        $jadwalRouteName = $guestMode ? 'guest.jadwal.index' : 'jadwal.index';

        $allMatkuls = isset($matkuls) ? $matkuls : collect();
        if (!($allMatkuls instanceof \Illuminate\Support\Collection)) {
            $allMatkuls = collect($allMatkuls);
        }

        $monthLabel = $calendarMonth->translatedFormat('F Y');
        $prevMonth = $calendarMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $calendarMonth->copy()->addMonth()->format('Y-m');
        $selectedDateKey = $selectedDate->toDateString();
        $dayNameMap = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        $jenisColor = [
            'kuliah' => 'bg-emerald-500',
            'lomba' => 'bg-blue-500',
            'lainnya' => 'bg-indigo-500',
            'libur' => 'bg-rose-500',
            'uts' => 'bg-amber-500',
            'uas' => 'bg-amber-500',
        ];

        $agendaCount = $jadwals->count();
        $kuliahCount = $jadwals->where('jenis', 'kuliah')->count();
        $liburCount = $jadwals->whereIn('jenis', ['libur', 'lainnya'])->count();

        $kegiatanList = collect($kegiatanByDate[$selectedDateKey] ?? []);
        $selectedDayIndex = $selectedDate->dayOfWeek;
        $selectedDayName = Str::lower($dayNameMap[$selectedDayIndex] ?? $selectedDate->translatedFormat('l'));
        $isSelectedWeekend = $selectedDate->isWeekend();

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

        $matchesMatkulDay = function ($matkul, ?int $dayIndex, ?string $lowerDay) {
            if (!$matkul) {
                return false;
            }

            if ($dayIndex !== null && method_exists($matkul, 'occursOnWeekday')) {
                if ($matkul->occursOnWeekday($dayIndex)) {
                    return true;
                }
            }

            if ($lowerDay && method_exists($matkul, 'matchesDay')) {
                return $matkul->matchesDay($lowerDay);
            }

            $raw = (string) ($matkul->hari ?? '');
            if ($raw === '') {
                return false;
            }

            return collect(explode(';', $raw))
                ->map(fn ($segment) => Str::lower(trim($segment)))
                ->filter()
                ->contains($lowerDay);
        };

        $hasMatkulDayData = function ($matkul) {
            if (!$matkul) {
                return false;
            }

            if (method_exists($matkul, 'scheduleDays')) {
                return $matkul->scheduleDays()->isNotEmpty();
            }

            $raw = (string) ($matkul->hari ?? '');
            return trim($raw) !== '';
        };

        $extractSlotForDay = function ($matkul, ?int $dayIndex, ?string $lowerDay) {
            if (!$matkul) {
                return null;
            }

            if ($dayIndex !== null && method_exists($matkul, 'firstScheduleEntryByIndex')) {
                $slot = $matkul->firstScheduleEntryByIndex($dayIndex);
                if ($slot) {
                    return $slot;
                }
            }

            if (method_exists($matkul, 'firstScheduleEntry')) {
                $slot = $matkul->firstScheduleEntry($lowerDay);
                if ($slot) {
                    return $slot;
                }
            }

            return null;
        };

        $resolveMatkulStartTime = function ($matkul, ?int $dayIndex, ?string $lowerDay, ?array $slot = null) use ($extractSlotForDay, $formatLegacyTime) {
            if (!$matkul) {
                return null;
            }

            $resolvedSlot = $slot ?? $extractSlotForDay($matkul, $dayIndex, $lowerDay);

            return $resolvedSlot['jam_mulai']
                ?? (method_exists($matkul, 'primaryStartTime')
                    ? $matkul->primaryStartTime()
                    : $formatLegacyTime($matkul->jam_mulai ?? null));
        };

        $upcomingKegiatan = collect($kegiatanByDate ?? [])
            ->flatMap(function ($items, $dateKey) {
                return collect($items)->map(function ($item) use ($dateKey) {
                    $deadline = $item->tanggal_deadline
                        ? Carbon::parse($item->tanggal_deadline)
                        : Carbon::parse($dateKey);
                    $timeLabel = $item->waktu ? Carbon::parse($item->waktu)->format('H:i') : null;

                    return [
                        'nama' => $item->nama_kegiatan,
                        'tanggal' => $deadline,
                        'waktu' => $timeLabel,
                        'lokasi' => $item->lokasi,
                    ];
                });
            })
            ->sortBy('tanggal')
            ->take(4);

        $selectedDayMatkulEntries = $selectedDayEvents
            ->flatMap(function ($event) use ($selectedDayIndex, $selectedDayName, $matchesMatkulDay, $hasMatkulDayData, $extractSlotForDay) {
                $details = collect($event->matkul_details ?? []);
                $matched = $details->filter(fn ($matkul) => $matchesMatkulDay($matkul, $selectedDayIndex, $selectedDayName));
                if ($matched->isEmpty()) {
                    $matched = $details->filter(fn ($matkul) => ! $hasMatkulDayData($matkul));
                }
                return $matched
                    ->filter()
                    ->map(fn ($matkul) => [
                        'instance' => $matkul,
                        'slot' => $extractSlotForDay($matkul, $selectedDayIndex, $selectedDayName),
                    ]);
            })
            ->filter(fn ($detail) => isset($detail['instance']))
            ->unique(fn ($detail) => $detail['instance']->id ?? $detail['instance']->nama ?? spl_object_hash($detail['instance']))
            ->sortBy(function ($detail) use ($selectedDayIndex, $selectedDayName, $resolveMatkulStartTime, $normalizeTimeForSort) {
                $matkul = $detail['instance'];
                $slot = $detail['slot'] ?? null;
                return $normalizeTimeForSort($resolveMatkulStartTime($matkul, $selectedDayIndex, $selectedDayName, $slot));
            })
            ->values();

        if ($isSelectedWeekend) {
            $selectedDayMatkulEntries = collect();
        }

        $todayRoute = route($jadwalRouteName, array_merge(request()->except(['tanggal', 'bulan']), [
            'tanggal' => now()->toDateString(),
            'bulan' => now()->format('Y-m'),
        ]));
    @endphp

    <div class="space-y-8">
        <section class="rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-white p-6 shadow-sm dark:border-slate-800 dark:from-[#111827] dark:via-[#0f172a] dark:to-[#0f172a] dark:shadow-none">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-300">Agenda akademik</p>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ $monthLabel }}</h1>
                    <p class="text-sm text-gray-500 mt-1 dark:text-slate-300">
                        Pantau jadwal kuliah, agenda kampus, dan kegiatan pribadi Anda pada tampilan kalender yang baru.
                    </p>
                </div>
            <div class="flex flex-wrap gap-3">
                    @if($guestMode)
                        <button type="button"
                                class="inline-flex items-center gap-2 rounded-2xl bg-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-500 shadow cursor-not-allowed"
                                aria-disabled="true">
                            <span class="text-lg leading-none">+</span> Tambah Jadwal
                        </button>
                    @else
                        <a href="{{ route('jadwal.create') }}"
                           class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                            <span class="text-lg leading-none">+</span> Tambah Jadwal
                        </a>
                    @endif
                    <a href="{{ $todayRoute }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-indigo-100 px-4 py-2.5 text-sm font-semibold text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 dark:border-indigo-400/40 dark:text-indigo-200 dark:hover:bg-indigo-500/10">
                        Hari Ini
                    </a>
                    <div class="flex rounded-2xl border border-gray-200 dark:border-slate-700">
                    <a href="{{ route($jadwalRouteName, array_merge(request()->except(['bulan','tanggal']), ['bulan' => $prevMonth, 'tanggal' => $calendarMonth->copy()->subMonth()->format('Y-m-d')])) }}"
                       class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-indigo-600 dark:text-slate-200 dark:hover:text-indigo-300">
                            ← Sebelumnya
                        </a>
                        <a href="{{ route($jadwalRouteName, array_merge(request()->except(['bulan','tanggal']), ['bulan' => $nextMonth, 'tanggal' => $calendarMonth->copy()->addMonth()->format('Y-m-d')])) }}"
                           class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-indigo-600 dark:text-slate-200 dark:hover:text-indigo-300">
                            Berikutnya →
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[2fr_1.1fr]">
            <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Kalender interaktif</h2>
                    <div class="flex items-center gap-3 text-[11px] uppercase tracking-wider text-gray-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="h-1.5 w-5 rounded-full bg-emerald-500"></span> Kuliah
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-1.5 w-5 rounded-full bg-amber-500"></span> UTS/UAS
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-1.5 w-5 rounded-full bg-rose-500"></span> Libur
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="calendar-kegiatan-dot h-1.5 w-5 rounded-full"></span> Kegiatan
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold text-gray-500">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $hari)
                        <div class="py-2">{{ $hari }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-2 mt-2">
                    @foreach($calendarDays as $day)
                        @php
                            $dayKey = $day->toDateString();
                            $isCurrentMonth = $day->month === $calendarMonth->month;
                            $isSelected = $dayKey === $selectedDateKey;
                            $isKuliahWeekend = $day->isWeekend();
                            $dayEvents = collect($jadwalsByDate[$dayKey] ?? []);
                            if ($isKuliahWeekend) {
                                $dayEvents = $dayEvents->reject(fn ($event) => $event->jenis === 'kuliah');
                            }
                            $eventsCount = $dayEvents->count();
                            $swatches = $dayEvents->groupBy(fn ($event) => $event->jenis)
                                ->map(fn ($group, $jenis) => $jenisColor[$jenis] ?? 'bg-indigo-400')
                                ->values()
                                ->when($isKuliahWeekend, fn ($colors) => $colors->prepend($jenisColor['libur'] ?? 'bg-rose-500'))
                                ->take(3);
                            $textColor = $isCurrentMonth ? 'text-gray-900' : 'text-gray-400';
                            $dayIndex = $day->dayOfWeek;
                            $dayNameLookup = $dayNameMap[$dayIndex] ?? $day->format('l');
                            $dayKeyName = Str::lower($dayNameLookup);
                            $dayMatkulInstances = $dayEvents
                                ->flatMap(function ($event) use ($dayIndex, $dayKeyName, $matchesMatkulDay, $hasMatkulDayData) {
                                    $details = collect($event->matkul_details ?? []);
                                    $matched = $details->filter(fn ($matkul) => $matchesMatkulDay($matkul, $dayIndex, $dayKeyName));
                                    if ($matched->isEmpty()) {
                                        $matched = $details->filter(fn ($matkul) => ! $hasMatkulDayData($matkul));
                                    }
                                    return $matched;
                                })
                                ->unique(fn ($matkul) => $matkul->id ?? $matkul->nama ?? spl_object_hash($matkul))
                                ->sortBy(fn ($matkul) => $normalizeTimeForSort($resolveMatkulStartTime($matkul, $dayIndex, $dayKeyName)))
                                ->values();

                            if ($eventsCount === 0 || $isKuliahWeekend) {
                                $dayMatkulInstances = collect();
                            }

                            $hasMatkulEntries = $eventsCount > 0 && $dayMatkulInstances->isNotEmpty() && ! $isKuliahWeekend;
                            $hasKegiatan = !empty($kegiatanDays[$dayKey] ?? false);
                        @endphp
                        <a href="{{ route($jadwalRouteName, array_merge(request()->except(['tanggal','bulan']), ['tanggal' => $dayKey, 'bulan' => $day->format('Y-m')])) }}"
                           class="relative flex min-h-[90px] flex-col rounded-2xl border px-3 pb-3 pt-2 {{ $textColor }} {{ $isSelected ? 'ring-2 ring-indigo-400 bg-indigo-50' : 'bg-white hover:bg-gray-50' }}">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span>{{ $day->format('d') }}</span>
                                <div class="flex gap-1">
                                    @foreach($swatches as $color)
                                        <span class="h-1.5 w-3 rounded-full {{ $color }}"></span>
                                    @endforeach
                                    @if($hasKegiatan)
                                        <span class="calendar-kegiatan-dot h-1.5 w-3 rounded-full"></span>
                                    @endif
                                </div>
                            </div>
                            @if($hasMatkulEntries)
                                <ul class="mt-3 space-y-0.5 text-[11px] text-gray-600 text-left">
                                    @foreach($dayMatkulInstances->take(3) as $matkulEntry)
                                        @php
                                            $name = $matkulEntry->nama;
                                            $entry = method_exists($matkulEntry, 'firstScheduleEntryByIndex')
                                                ? $matkulEntry->firstScheduleEntryByIndex($dayIndex)
                                                : null;

                                            $start = $entry['jam_mulai'] ?? $matkulEntry->primaryStartTime() ?? $formatLegacyTime($matkulEntry->jam_mulai ?? null);
                                            $end = $entry['jam_selesai'] ?? $matkulEntry->primaryEndTime() ?? $formatLegacyTime($matkulEntry->jam_selesai ?? null);
                                            $room = $entry['ruangan'] ?? ($matkulEntry->primaryRoom() ?? $matkulEntry->ruangan ?? null);
                                            $timeLabel = $start && $end ? $start . ' - ' . $end : ($start ?: null);
                                        @endphp
                                        <li class="truncate">
                                            <span class="font-semibold">{{ $name }}</span>
                                            @if($timeLabel)
                                                <span class="text-gray-400"> • {{ $timeLabel }}</span>
                                            @endif
                                            @if($room)
                                                <span class="text-gray-400"> • {{ $room }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                    @if($dayMatkulInstances->count() > 3)
                                        <li class="text-indigo-500">+{{ $dayMatkulInstances->count() - 3 }} lainnya</li>
                                    @endif
                                </ul>
                            @else
                                <p class="mt-auto text-[11px] text-gray-300">
                                    {{ $isKuliahWeekend ? 'Libur kuliah' : '—' }}
                                </p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">Agenda tanggal</p>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $selectedDate->translatedFormat('l, d F Y') }}</h3>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-200">
                        {{ $selectedDayEvents->count() }} agenda
                    </span>
                </div>

                @if($isSelectedWeekend)
                    <div class="mt-3 rounded-2xl border border-rose-100 bg-rose-50/70 px-4 py-3 text-xs font-semibold text-rose-700 shadow-sm dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
                        Akhir pekan: jadwal kuliah otomatis libur.
                    </div>
                @endif

                <div class="mt-4 space-y-4">
                    @forelse($selectedDayEvents as $event)
                        @php
                            $rangeLabel = Carbon::parse($event->tanggal_mulai)->translatedFormat('d M') .
                                ($event->tanggal_selesai && $event->tanggal_selesai !== $event->tanggal_mulai
                                    ? ' - ' . Carbon::parse($event->tanggal_selesai)->translatedFormat('d M')
                                    : '');
                            $badgeClass = $jenisColor[$event->jenis] ?? 'bg-indigo-500';
                            $matkulDetailsRaw = collect($event->matkul_details ?? []);
                            $filteredMatkuls = $matkulDetailsRaw->filter(fn ($matkul) => $matchesMatkulDay($matkul, $selectedDayIndex, $selectedDayName));
                            if ($filteredMatkuls->isEmpty()) {
                                $filteredMatkuls = $matkulDetailsRaw->filter(fn ($matkul) => ! $hasMatkulDayData($matkul));
                            }
                            $matkulDetails = $filteredMatkuls
                                ->map(function ($matkul) use ($selectedDayIndex, $selectedDayName, $extractSlotForDay) {
                                    return [
                                        'instance' => $matkul,
                                        'slot' => $extractSlotForDay($matkul, $selectedDayIndex, $selectedDayName),
                                    ];
                                })
                                ->filter(fn ($detail) => isset($detail['instance']))
                                ->values();
                        @endphp
                        <article class="rounded-2xl border border-gray-100 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/50">
                            <header class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $event->catatan_tambahan ?: 'Tidak ada catatan' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-slate-400">{{ $rangeLabel }}</p>
                                </div>
                                <span class="inline-flex items-center gap-2 rounded-full {{ $badgeClass }} px-3 py-1 text-[11px] font-semibold text-white dark:text-white/90">
                                    {{ strtoupper($event->jenis ?? 'AGENDA') }}
                                </span>
                            </header>

                            @if($matkulDetails->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach($matkulDetails as $detail)
                                        @php
                                            $matkul = $detail['instance'];
                                            $slot = $detail['slot'] ?? null;
                                            $startTime = $slot['jam_mulai']
                                                ?? (method_exists($matkul, 'primaryStartTime')
                                                    ? $matkul->primaryStartTime()
                                                    : $formatLegacyTime($matkul->jam_mulai ?? null));
                                            $endTime = $slot['jam_selesai']
                                                ?? (method_exists($matkul, 'primaryEndTime')
                                                    ? $matkul->primaryEndTime()
                                                    : $formatLegacyTime($matkul->jam_selesai ?? null));
                                            $timeLabel = $startTime && $endTime ? $startTime . ' - ' . $endTime : ($startTime ?: null);
                                            $kelasLabel = method_exists($matkul, 'primaryClass') ? $matkul->primaryClass() : ($matkul->kelas ?? null);
                                            $ruanganLabel = $slot['ruangan']
                                                ?? (method_exists($matkul, 'primaryRoom') ? $matkul->primaryRoom() : null)
                                                ?? ($matkul->ruangan ?? null);
                                        @endphp
                                        <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 text-sm dark:border-slate-800 dark:bg-slate-800/60">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $matkul->nama ?? 'Matkul' }}</p>
                                            <div class="mt-2 flex flex-wrap gap-3 text-[11px] text-gray-600 dark:text-slate-300">
                                                @if($timeLabel)
                                                    <span class="inline-flex items-center gap-1">
                                                        <svg class="h-3.5 w-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                  d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        {{ $timeLabel }}
                                                    </span>
                                                @endif
                                                @if($kelasLabel)
                                                    <span class="inline-flex items-center gap-1">
                                                        <svg class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                  d="M5 12h14M5 12a5 5 0 010-10h14a5 5 0 010 10M5 12a5 5 0 000 10h14a5 5 0 000-10" />
                                                        </svg>
                                                        Kelas {{ $kelasLabel }}
                                                    </span>
                                                @endif
                                                @if($ruanganLabel)
                                                    <span class="inline-flex items-center gap-1">
                                                        <svg class="h-3.5 w-3.5 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                  d="M12 21c-4.418 0-8-3.134-8-7s3.582-7 8-7 8 3.134 8 7-3.582 7-8 7z" />
                                                        </svg>
                                                        {{ $ruanganLabel }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-200 p-6 text-center dark:border-slate-700">
                            <p class="text-sm font-semibold text-gray-700 dark:text-white">
                                {{ $isSelectedWeekend ? 'Libur kuliah (akhir pekan).' : 'Belum ada jadwal pada tanggal ini.' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                {{ $isSelectedWeekend ? 'Sabtu/Minggu otomatis bebas perkuliahan.' : 'Tambahkan agenda baru atau pilih tanggal berbeda.' }}
                            </p>
                        </div>
                    @endforelse

                    @if($selectedDayEvents->isNotEmpty() && $selectedDayMatkulEntries->isNotEmpty())
                        <div class="rounded-2xl border border-indigo-50 bg-indigo-50/40 p-4 space-y-2 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-200">Matkul hari ini</p>
                                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-100">{{ $selectedDayMatkulEntries->count() }} matkul</span>
                            </div>
                            <ul class="space-y-2 text-sm text-gray-700 dark:text-slate-100">
                                @foreach($selectedDayMatkulEntries as $detail)
                                    @php
                                        $matkul = $detail['instance'];
                                        $slot = $detail['slot'] ?? null;
                                        $startTime = $slot['jam_mulai']
                                            ?? (method_exists($matkul, 'primaryStartTime')
                                                ? $matkul->primaryStartTime()
                                                : $formatLegacyTime($matkul->jam_mulai ?? null));
                                        $endTime = $slot['jam_selesai']
                                            ?? (method_exists($matkul, 'primaryEndTime')
                                                ? $matkul->primaryEndTime()
                                                : $formatLegacyTime($matkul->jam_selesai ?? null));
                                        $timeLabel = $startTime && $endTime ? $startTime . ' - ' . $endTime : ($startTime ?: null);
                                        $kelasLabel = $slot['kelas']
                                            ?? (method_exists($matkul, 'primaryClass') ? $matkul->primaryClass() : ($matkul->kelas ?? null));
                                        $ruanganLabel = $slot['ruangan']
                                            ?? (method_exists($matkul, 'primaryRoom') ? $matkul->primaryRoom() : null)
                                            ?? ($matkul->ruangan ?? null);
                                    @endphp
                                    <li class="rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-slate-900/60 dark:border dark:border-slate-800">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-gray-900 dark:text-white">{{ $matkul->nama ?? 'Matkul' }}</p>
                                                <div class="mt-1 flex flex-wrap gap-3 text-[11px] text-gray-600 dark:text-slate-300">
                                                    @if($timeLabel)
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg class="h-3.5 w-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                      d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            {{ $timeLabel }}
                                                        </span>
                                                    @endif
                                                    @if($kelasLabel)
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                      d="M5 12h14M5 12a5 5 0 010-10h14a5 5 0 010 10M5 12a5 5 0 000 10h14a5 5 0 000-10" />
                                                            </svg>
                                                            Kelas {{ $kelasLabel }}
                                                        </span>
                                                    @endif
                                                    @if($ruanganLabel)
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg class="h-3.5 w-3.5 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                                      d="M12 21c-4.418 0-8-3.134-8-7s3.582-7 8-7 8 3.134 8 7-3.582 7-8 7z" />
                                                            </svg>
                                                            {{ $ruanganLabel }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-[11px] font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-100">
                                                Matkul
                                            </span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($kegiatanList->isNotEmpty())
                        <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-4 space-y-2 dark:border-slate-800 dark:bg-slate-900/50">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-300">Kegiatan terkait</p>
                            <ul class="space-y-2 text-sm text-gray-700 dark:text-slate-100">
                                @foreach($kegiatanList as $kegiatan)
                                    @php
                                        $deadline = $kegiatan->tanggal_deadline
                                            ? Carbon::parse($kegiatan->tanggal_deadline)->translatedFormat('d M Y')
                                            : 'Tanggal belum ditentukan';
                                        $timeLabel = $kegiatan->waktu ? Carbon::parse($kegiatan->waktu)->format('H:i') : null;
                                    @endphp
                                    <li class="flex flex-col rounded-xl bg-white px-3 py-2 shadow-sm dark:bg-slate-900/60 dark:border dark:border-slate-800">
                                        <span class="font-semibold dark:text-white">{{ $kegiatan->nama_kegiatan }}</span>
                                        <span class="text-xs text-gray-500 dark:text-slate-400">
                                            {{ $deadline }}
                                            @if($timeLabel)
                                                • {{ $timeLabel }}
                                            @endif
                                            @if($kegiatan->lokasi)
                                                • {{ $kegiatan->lokasi }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Agenda mendatang</p>
                        <h3 class="text-lg font-semibold text-gray-900">Kegiatan prioritas</h3>
                    </div>
                    <a href="{{ route('kegiatan.index') }}" class="text-xs font-semibold text-indigo-600 hover:underline">Kelola kegiatan</a>
                </div>
                @if($upcomingKegiatan->isEmpty())
                    <p class="text-sm text-gray-500">Belum ada kegiatan yang terjadwal.</p>
                @else
                    <ul class="space-y-3">
                        @foreach($upcomingKegiatan as $upcoming)
                            <li class="flex items-center justify-between rounded-2xl border border-gray-100 px-4 py-3 text-sm shadow-sm">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $upcoming['nama'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $upcoming['tanggal']->translatedFormat('l, d M Y') }}
                                        @if($upcoming['waktu'])
                                            • {{ $upcoming['waktu'] }}
                                        @endif
                                        @if($upcoming['lokasi'])
                                            • {{ $upcoming['lokasi'] }}
                                        @endif
                                    </p>
                                </div>
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-[11px] font-semibold text-gray-700">
                                    {{ $upcoming['tanggal']->diffForHumans(null, true) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Navigasi cepat</p>
                        <h3 class="text-lg font-semibold text-gray-900">Akses fitur terkait</h3>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @if($guestMode)
                        <span class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-500 cursor-not-allowed" aria-disabled="true">
                            Kelola Matkul (login)
                        </span>
                        <span class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-500 cursor-not-allowed" aria-disabled="true">
                            Kelola Kegiatan (login)
                        </span>
                        <span class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-500 cursor-not-allowed" aria-disabled="true">
                            Tambah Jadwal (nonaktif)
                        </span>
                        <a href="{{ route('guest.home') }}"
                           class="rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 hover:border-gray-300">
                            Lihat Dashboard
                        </a>
                    @else
                        <a href="{{ route('matkul.index') }}"
                           class="rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700 hover:border-indigo-200 hover:bg-white">
                            Kelola Matkul
                        </a>
                        <a href="{{ route('kegiatan.index') }}"
                           class="rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 hover:border-gray-300">
                            Kelola Kegiatan
                        </a>
                        <a href="{{ route('jadwal.create') }}"
                           class="rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 hover:border-gray-300">
                            Tambah Jadwal
                        </a>
                        <a href="{{ route('dashboard') }}"
                           class="rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 hover:border-gray-300">
                            Lihat Dashboard
                        </a>
                    @endif
                </div>
                <p class="mt-4 text-xs text-gray-500">
                    Gunakan menu di atas untuk mempercepat pencatatan matkul, kegiatan, atau meninjau statistik akademik.
                </p>
            </div>
        </section>
    </div>
@endsection
