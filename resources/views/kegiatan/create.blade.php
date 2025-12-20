@extends('layouts.app')

@section('page_title', 'Tambah Kegiatan')

@section('content')
    @if($jadwals->isEmpty())
        <div class="max-w-3xl mx-auto bg-white border rounded-3xl shadow-sm p-8 text-center space-y-3 dark:bg-slate-900 dark:border-slate-800">
            <p class="text-lg font-semibold text-gray-900 dark:text-slate-100">Belum ada jadwal utama</p>
            <p class="text-sm text-gray-500 dark:text-slate-400">Buat jadwal terlebih dahulu agar bisa menambahkan kegiatan detail.</p>
            <a href="{{ route('jadwal.create') }}"
               class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                + Buat Jadwal
            </a>
        </div>
    @else
        @php
            $defaultJadwalId = old('jadwal_id', request('jadwal_id', $jadwals->first()->id));
            $selectedJadwal = $jadwals->firstWhere('id', $defaultJadwalId) ?? $jadwals->first();
            $jadwalMap = $jadwals->mapWithKeys(function ($item) {
                $mulai = \Illuminate\Support\Carbon::parse($item->tanggal_mulai)->translatedFormat('d M Y');
                $selesai = \Illuminate\Support\Carbon::parse($item->tanggal_selesai)->translatedFormat('d M Y');
                $matkulNames = collect($item->matkul_names ?? [])->take(3)->join(', ');
                $jenisLabel = ucfirst($item->jenis ?? 'Agenda');
                $ringkasan = $item->catatan_tambahan ?: $jenisLabel;
                return [$item->id => [
                    'id' => $item->id,
                    'ringkasan' => $ringkasan,
                    'rentang' => $mulai . ' - ' . $selesai,
                    'matkul' => $matkulNames ?: '—',
                    'semester' => $item->semester ? 'Semester ' . $item->semester : 'Semester belum ditentukan',
                    'jenis' => $jenisLabel,
                    'catatan' => $item->catatan_tambahan ?: 'Tidak ada catatan',
                ]];
            });
        @endphp
        <div class="grid gap-6 lg:grid-cols-12">
            <aside class="lg:col-span-4 space-y-4">
                <div class="bg-white border rounded-3xl shadow-sm p-5 space-y-3 dark:bg-slate-900 dark:border-slate-800" id="jadwalSummaryCard">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-400">Jadwal terpilih</p>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100" id="summaryName">
                            {{ $selectedJadwal->catatan_tambahan ?: ucfirst($selectedJadwal->jenis ?? 'Agenda') }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-slate-400" id="summaryRange">
                            {{ \Illuminate\Support\Carbon::parse($selectedJadwal->tanggal_mulai)->translatedFormat('d M Y') }} -
                            {{ \Illuminate\Support\Carbon::parse($selectedJadwal->tanggal_selesai)->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <dl class="text-xs text-gray-600 space-y-1 dark:text-slate-300">
                        <div class="flex justify-between">
                            <dt>Jenis</dt>
                            <dd id="summaryJenis">{{ ucfirst($selectedJadwal->jenis ?? 'Agenda') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Semester</dt>
                            <dd id="summarySemester">{{ $selectedJadwal->semester ? 'Semester ' . $selectedJadwal->semester : 'Belum ditentukan' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Matkul</dt>
                            <dd id="summaryMatkul">{{ collect($selectedJadwal->matkul_names ?? [])->join(', ') ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Catatan</dt>
                            <dd id="summaryCatatan">{{ $selectedJadwal->catatan_tambahan ?: 'Tidak ada catatan' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white border rounded-3xl shadow-sm p-5 space-y-3 dark:bg-slate-900 dark:border-slate-800">
                    <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">Pilih Jadwal Tujuan</p>
                    <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
                        @foreach($jadwals as $item)
                            @php
                                $isActive = $item->id === $selectedJadwal->id;
                            @endphp
                            <button type="button"
                                    data-pick-jadwal
                                    data-id="{{ $item->id }}"
                                    class="w-full text-left rounded-2xl border px-4 py-3 text-sm {{ $isActive ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400/70 dark:bg-indigo-900/30 dark:text-indigo-100' : 'border-gray-100 bg-gray-50 text-gray-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200' }}">
                                <p class="font-semibold">
                                    {{ $item->catatan_tambahan ?: ucfirst($item->jenis ?? 'Agenda') }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ \Illuminate\Support\Carbon::parse($item->tanggal_mulai)->translatedFormat('d M') }}
                                    -
                                    {{ \Illuminate\Support\Carbon::parse($item->tanggal_selesai)->translatedFormat('d M') }}
                                </p>
                            </button>
                        @endforeach
                    </div>
                </div>
            </aside>

            <div class="lg:col-span-8 bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-800">
                <div>
                    <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-300">Agenda Detail</p>
                    <h2 class="text-2xl font-bold text-gray-900 mt-1 dark:text-slate-100">Tambah Kegiatan</h2>
                    <p class="text-sm text-gray-500 mt-2 dark:text-slate-400">
                        Pilih jadwal tujuan di sisi kiri, lalu isi informasi kegiatan di bawah ini.
                    </p>
                </div>

                @include('kegiatan.partials.form', [
                    'action' => route('kegiatan.store'),
                    'method' => 'POST',
                    'kegiatan' => null,
                    'jadwals' => $jadwals,
                    'submitLabel' => 'Simpan Kegiatan',
                ])
            </div>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const jadwalMap = @json($jadwalMap);
                    const select = document.getElementById('jadwal_id');
                    const summaryName = document.getElementById('summaryName');
                    const summaryRange = document.getElementById('summaryRange');
                    const summaryMatkul = document.getElementById('summaryMatkul');
                    const summaryJenis = document.getElementById('summaryJenis');
                    const summarySemester = document.getElementById('summarySemester');
                    const summaryCatatan = document.getElementById('summaryCatatan');

                    const updateSummary = (id) => {
                        const data = jadwalMap[id];
                        if (!data) return;
                        if (summaryName) summaryName.textContent = data.ringkasan ?? 'Tidak diketahui';
                        if (summaryRange) summaryRange.textContent = data.rentang ?? '-';
                        if (summaryMatkul) summaryMatkul.textContent = data.matkul ?? '—';
                        if (summaryJenis) summaryJenis.textContent = data.jenis ?? 'Agenda';
                        if (summarySemester) summarySemester.textContent = data.semester ?? 'Belum ditentukan';
                        if (summaryCatatan) summaryCatatan.textContent = data.catatan ?? 'Tidak ada catatan';
                    };

                    select?.addEventListener('change', (event) => {
                        updateSummary(event.target.value);
                    });

                    updateSummary(select?.value);

                    const activeClasses = ['border-indigo-500', 'bg-indigo-50', 'text-indigo-700', 'dark:border-indigo-400/70', 'dark:bg-indigo-900/30', 'dark:text-indigo-100'];
                    const inactiveClasses = ['border-gray-100', 'bg-gray-50', 'text-gray-700', 'dark:border-slate-700', 'dark:bg-slate-900/60', 'dark:text-slate-200'];

                    document.querySelectorAll('[data-pick-jadwal]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const id = button.dataset.id;
                            if (!id || !select) return;
                            select.value = id;
                            select.dispatchEvent(new Event('change'));
                            document.querySelectorAll('[data-pick-jadwal]').forEach((btn) => {
                                btn.classList.remove(...activeClasses);
                                btn.classList.add(...inactiveClasses);
                            });
                            button.classList.add(...activeClasses);
                            button.classList.remove(...inactiveClasses);
                        });
                    });
                });
            </script>
        @endpush
    @endif
@endsection
