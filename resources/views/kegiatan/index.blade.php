@extends('layouts.app')

@section('page_title', 'Rincian Kegiatan')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold">Timeline Detail</p>
                    <h2 class="text-2xl font-bold text-gray-900">Kegiatan per Jadwal</h2>
                    <p class="text-sm text-gray-500 mt-1">Semua sub-aktivitas yang terhubung ke jadwal utama.</p>
                </div>
                <a href="{{ route('kegiatan.create') }}"
                   class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                    + Tambah Kegiatan
                </a>
            </div>
        </div>

        <div class="space-y-6">
            @forelse($jadwals as $jadwal)
                <div class="bg-white border rounded-3xl shadow-sm p-6 space-y-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm uppercase tracking-wide text-gray-500">Jadwal</p>
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $jadwal->catatan_tambahan ?: ucfirst($jadwal->jenis ?? 'Agenda') }}
                            </h3>
                            <p class="text-xs text-gray-500">
                                {{ \Illuminate\Support\Carbon::parse($jadwal->tanggal_mulai)->translatedFormat('d M Y') }} —
                                {{ \Illuminate\Support\Carbon::parse($jadwal->tanggal_selesai)->translatedFormat('d M Y') }}
                                | {{ ucfirst($jadwal->jenis ?? 'Agenda') }}
                                @if($jadwal->semester)
                                    | Semester {{ $jadwal->semester }}
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-2 text-xs">
                            <a href="{{ route('jadwal.edit', $jadwal) }}"
                               class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-1.5 text-gray-600 hover:border-indigo-200 hover:text-indigo-600">
                                Edit Jadwal
                            </a>
                            <a href="{{ route('kegiatan.create', ['jadwal_id' => $jadwal->id]) }}"
                               class="inline-flex items-center rounded-xl border border-indigo-200 px-3 py-1.5 text-indigo-600 hover:bg-indigo-50">
                                + Tambah Kegiatan
                            </a>
                        </div>
                    </div>

                    @if($jadwal->kegiatans->count())
                        <div class="space-y-3">
                            @foreach($jadwal->kegiatans as $kegiatan)
                                @php
                                    $statusClasses = match($kegiatan->status) {
                                        'selesai' => 'bg-emerald-50 text-emerald-700',
                                        'berlangsung' => 'bg-amber-50 text-amber-700',
                                        default => 'bg-gray-100 text-gray-600'
                                    };
                                @endphp
                                <div class="rounded-2xl border border-gray-100 p-4 shadow-sm flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $kegiatan->nama_kegiatan }}</p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            {{ \Illuminate\Support\Carbon::parse($kegiatan->waktu)->translatedFormat('l, d F Y H:i') }}
                                            @if($kegiatan->lokasi)
                                                • {{ $kegiatan->lokasi }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                            {{ ucfirst(str_replace('_', ' ', $kegiatan->status)) }}
                                        </span>
                                        <a href="{{ route('kegiatan.edit', $kegiatan) }}"
                                           class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-indigo-200 hover:text-indigo-600">
                                            Edit
                                        </a>
                                        <form action="{{ route('kegiatan.destroy', $kegiatan) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('Hapus kegiatan ini?')"
                                                    class="inline-flex items-center rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-500">
                            Belum ada kegiatan terdaftar untuk jadwal ini.
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white border rounded-3xl shadow-sm p-8 text-center space-y-2">
                    <p class="text-lg font-semibold text-gray-900">Belum ada jadwal</p>
                    <p class="text-sm text-gray-500">Buat jadwal utama terlebih dahulu lalu tambahkan kegiatan detailnya.</p>
                    <a href="{{ route('jadwal.create') }}"
                       class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                        + Jadwal Baru
                    </a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
