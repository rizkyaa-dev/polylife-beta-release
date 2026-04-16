@extends('layouts.app')

@section('page_title', 'Manage Jadwal')

@section('content')
    @php
        use Illuminate\Support\Carbon;
        use Illuminate\Support\Str;

        $filters = $filters ?? [];
        $summary = $summary ?? [];
        $search = (string) ($filters['q'] ?? '');
        $jenis = (string) ($filters['jenis'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'latest');

        $jenisOptions = [
            '' => 'Semua jenis',
            'kuliah' => 'Kuliah',
            'libur' => 'Libur',
            'uts' => 'UTS',
            'uas' => 'UAS',
            'lomba' => 'Lomba',
            'lainnya' => 'Lainnya',
        ];

        $statusOptions = [
            '' => 'Semua status',
            'running' => 'Sedang berjalan',
            'upcoming' => 'Mendatang',
            'completed' => 'Selesai',
        ];

        $sortOptions = [
            'latest' => 'Tanggal mulai terbaru',
            'oldest' => 'Tanggal mulai terlama',
            'updated' => 'Terakhir diperbarui',
            'ending_soon' => 'Paling cepat selesai',
        ];

        $todayRoute = route('jadwal.index', [
            'tanggal' => now()->toDateString(),
            'bulan' => now()->format('Y-m'),
        ]);

        $badgeMap = [
            'kuliah' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200',
            'libur' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200',
            'uts' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200',
            'uas' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200',
            'lomba' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-200',
            'lainnya' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200',
        ];
    @endphp

    <div class="space-y-6">
        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Mode Kelola</p>
                    <h1 class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">Manage Jadwal</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-slate-300">
                        Rapikan agenda kuliah, kegiatan, dan agenda akademik lain dari satu layar. Cari cepat, filter rentang tanggal, lalu langsung edit atau hapus tanpa kembali ke kalender.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('jadwal.create') }}"
                       class="inline-flex items-center rounded-2xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                        + Jadwal Baru
                    </a>
                    <a href="{{ route('jadwal.index') }}"
                       class="inline-flex items-center rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600">
                        Kembali ke Kalender
                    </a>
                    <a href="{{ $todayRoute }}"
                       class="inline-flex items-center rounded-2xl border border-indigo-100 px-4 py-2.5 text-sm font-semibold text-indigo-600 transition hover:border-indigo-200 hover:bg-indigo-50 dark:border-indigo-400/40 dark:text-indigo-200 dark:hover:bg-indigo-500/10">
                        Hari Ini
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Total agenda</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($summary['total'] ?? 0)) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Seluruh jadwal yang tersimpan.</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Agenda hari ini</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($summary['today'] ?? 0)) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Item yang aktif pada tanggal hari ini.</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Agenda kuliah</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($summary['kuliah'] ?? 0)) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Jadwal yang terhubung ke perkuliahan.</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Sudah selesai</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($summary['completed'] ?? 0)) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Selesai otomatis atau ditandai selesai.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                <a href="{{ route('matkul.index') }}"
                   class="rounded-2xl border border-gray-100 bg-gray-50/70 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-indigo-200 hover:bg-white dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-200 dark:hover:border-indigo-500/40">
                    Kelola Matkul
                </a>
                <a href="{{ route('kegiatan.index') }}"
                   class="rounded-2xl border border-gray-100 bg-gray-50/70 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-indigo-200 hover:bg-white dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-200 dark:hover:border-indigo-500/40">
                    Kelola Kegiatan
                </a>
                <a href="{{ route('reminder.index') }}"
                   class="rounded-2xl border border-gray-100 bg-gray-50/70 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:border-indigo-200 hover:bg-white dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-200 dark:hover:border-indigo-500/40">
                    Kelola Reminder
                </a>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <form method="GET" action="{{ route('jadwal.manage') }}" class="grid gap-4 xl:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto]">
                <div class="space-y-2">
                    <label for="q" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Cari agenda</label>
                    <input id="q"
                           type="text"
                           name="q"
                           value="{{ $search }}"
                           placeholder="Catatan, judul, atau lokasi"
                           class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                </div>
                <div class="space-y-2">
                    <label for="jenis" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Jenis</label>
                    <select id="jenis"
                            name="jenis"
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                        @foreach($jenisOptions as $value => $label)
                            <option value="{{ $value }}" @selected($jenis === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="status" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Status</label>
                    <select id="status"
                            name="status"
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="date_from" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Dari tanggal</label>
                    <input id="date_from"
                           type="date"
                           name="date_from"
                           value="{{ $dateFrom }}"
                           class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                </div>
                <div class="space-y-2">
                    <label for="date_to" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Sampai</label>
                    <input id="date_to"
                           type="date"
                           name="date_to"
                           value="{{ $dateTo }}"
                           class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                </div>
                <div class="space-y-2">
                    <label for="sort" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Urutkan</label>
                    <div class="flex items-end gap-3">
                        <select id="sort"
                                name="sort"
                                class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                            @foreach($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex items-end gap-3 xl:col-span-6">
                    <button type="submit"
                            class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">
                        Terapkan
                    </button>
                    <a href="{{ route('jadwal.manage') }}"
                       class="inline-flex items-center rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600 transition hover:border-gray-300 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Hasil Pengelolaan</p>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $jadwals->total() }} agenda ditemukan</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Halaman {{ $jadwals->currentPage() }} dari {{ $jadwals->lastPage() }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($search !== '')
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200">
                            Cari: {{ Str::limit($search, 24) }}
                        </span>
                    @endif
                    @if($jenis !== '')
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ $jenisOptions[$jenis] ?? ucfirst($jenis) }}
                        </span>
                    @endif
                    @if($status !== '')
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ $statusOptions[$status] ?? ucfirst($status) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-5 space-y-4">
                @forelse($jadwals as $jadwal)
                    @php
                        $today = Carbon::today();
                        $start = Carbon::parse($jadwal->tanggal_mulai);
                        $end = Carbon::parse($jadwal->tanggal_selesai);
                        $isCompleted = (bool) $jadwal->is_completed || $end->lt($today);
                        $isRunning = ! $isCompleted && $start->lte($today) && $end->gte($today);
                        $statusLabel = $isCompleted ? 'Selesai' : ($isRunning ? 'Berlangsung' : 'Mendatang');
                        $statusClass = $isCompleted
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200'
                            : ($isRunning
                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200');
                        $jenisClass = $badgeMap[$jadwal->jenis ?? 'lainnya'] ?? $badgeMap['lainnya'];
                        $agendaTitle = trim((string) ($jadwal->catatan_tambahan ?: $jadwal->title ?: 'Agenda tanpa judul'));
                        $matkulNames = collect($jadwal->matkul_names ?? [])->filter()->values();
                        $primaryMatkul = $jadwal->primary_matkul;
                        $primaryRoom = $primaryMatkul
                            ? (method_exists($primaryMatkul, 'primaryRoom') ? $primaryMatkul->primaryRoom() : ($primaryMatkul->ruangan ?? null))
                            : null;
                        $location = trim((string) ($jadwal->location ?: $primaryRoom ?: ''));
                        $summaryText = trim((string) ($jadwal->title ?: ''));
                    @endphp
                    <article class="rounded-2xl border border-gray-100 bg-gray-50/60 p-5 shadow-sm transition hover:border-indigo-200 hover:bg-white dark:border-slate-800 dark:bg-slate-900/50 dark:hover:border-indigo-500/30 dark:hover:bg-slate-900/80">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $jenisClass }}">
                                        {{ strtoupper($jenisOptions[$jadwal->jenis] ?? ($jadwal->jenis ?? 'AGENDA')) }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                        {{ strtoupper($statusLabel) }}
                                    </span>
                                </div>

                                <h3 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">{{ Str::limit($agendaTitle, 120) }}</h3>

                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-slate-400">
                                    <span>{{ $start->translatedFormat('d M Y') }} - {{ $end->translatedFormat('d M Y') }}</span>
                                    @if($jadwal->semester)
                                        <span>Semester {{ $jadwal->semester }}</span>
                                    @endif
                                    @if($location !== '')
                                        <span>{{ $location }}</span>
                                    @endif
                                </div>

                                @if($summaryText !== '')
                                    <p class="mt-3 text-sm text-gray-600 dark:text-slate-300">{{ Str::limit($summaryText, 150) }}</p>
                                @endif

                                @if($matkulNames->isNotEmpty())
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach($matkulNames->take(4) as $matkulName)
                                            <span class="inline-flex items-center rounded-full border border-indigo-100 bg-white px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/30 dark:bg-slate-950/50 dark:text-indigo-200">
                                                {{ $matkulName }}
                                            </span>
                                        @endforeach
                                        @if($matkulNames->count() > 4)
                                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-600 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-300">
                                                +{{ $matkulNames->count() - 4 }} lainnya
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a href="{{ route('jadwal.edit', $jadwal) }}"
                                   class="inline-flex items-center rounded-2xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-gray-300 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600">
                                    Edit
                                </a>
                                <a href="{{ route('jadwal.confirm-delete', $jadwal) }}"
                                   class="inline-flex items-center rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200 dark:hover:bg-rose-500/20">
                                    Hapus
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/60 p-8 text-center dark:border-slate-700 dark:bg-slate-900/40">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">Belum ada jadwal yang cocok.</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-slate-400">Ubah filter atau buat agenda baru dari halaman ini.</p>
                    </div>
                @endforelse
            </div>

            @if($jadwals->hasPages())
                <div class="mt-6">
                    {{ $jadwals->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
