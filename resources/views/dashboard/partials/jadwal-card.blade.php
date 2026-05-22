        {{-- Kartu: Jadwal Hari Ini --}}
        @php
            use Illuminate\Support\Carbon;
            use Illuminate\Support\Str;
        @endphp

        <section class="bg-white rounded-2xl shadow-sm border p-4 sm:p-5 h-full flex flex-col min-w-0 dark:bg-slate-900 dark:border-slate-800">
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
                                                        â€¢ {{ $deadlineDate }}
                                                        @if($timeLabel)
                                                            â€¢ {{ $timeLabel }}
                                                        @endif
                                                        @if($kegiatan->lokasi)
                                                            â€¢ {{ $kegiatan->lokasi }}
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
        
