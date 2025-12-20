@extends('layouts.app')

@section('page_title', 'Daftar Matkul')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold">Data Akademik</p>
                    <h2 class="text-2xl font-bold text-gray-900">Mata Kuliah Tersimpan</h2>
                    <p class="text-sm text-gray-500 mt-1">Gunakan daftar ini untuk mengisi jadwal dan kegiatan lebih cepat.</p>
                </div>
                <a href="{{ route('matkul.create') }}"
                   class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                    + Matkul Baru
                </a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($matkuls as $matkul)
                <div class="bg-white border rounded-3xl shadow-sm p-5 space-y-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-500">{{ $matkul->kode }}</p>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $matkul->nama }}</h3>
                            <p class="text-xs text-gray-500">
                                Semester {{ $matkul->semester ?? '-' }} • {{ $matkul->sks ?? '-' }} SKS
                            </p>
                        </div>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl border"
                              style="border-color: {{ $matkul->warna_label ?? '#8181FF' }};">
                            <span class="h-4 w-4 rounded-full"
                                  style="background-color: {{ $matkul->warna_label ?? '#8181FF' }};"></span>
                        </span>
                    </div>

                    <dl class="text-xs text-gray-600 space-y-1">
                        <div class="flex justify-between">
                            <dt>Kelas</dt>
                            <dd>
                                @if(method_exists($matkul, 'classList') && $matkul->classList()->isNotEmpty())
                                    {{ $matkul->classList()->implode(', ') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Dosen</dt>
                            <dd>{{ $matkul->dosen ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Ruangan</dt>
                            <dd>
                                @if(method_exists($matkul, 'scheduleRooms') && $matkul->scheduleRooms()->isNotEmpty())
                                    {{ $matkul->scheduleRooms()->implode(', ') }}
                                @elseif($matkul->ruangan)
                                    {{ $matkul->ruangan }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Hari</dt>
                            <dd>
                                @if(method_exists($matkul, 'scheduleDays') && $matkul->scheduleDays()->isNotEmpty())
                                    {{ $matkul->scheduleDays()->implode(', ') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Jam</dt>
                            <dd class="text-right">
                                @php
                                    $scheduleEntries = method_exists($matkul, 'scheduleEntries') ? $matkul->scheduleEntries() : collect();
                                @endphp
                                @if($scheduleEntries->isNotEmpty())
                                    <div class="space-y-0.5 text-right">
                                        @foreach($scheduleEntries as $entry)
                                            <p>
                                                @if(!empty($entry['hari']))
                                                    <span class="font-semibold">{{ \Illuminate\Support\Str::title($entry['hari']) }}</span>
                                                    •
                                                @endif
                                                @if(!empty($entry['jam_mulai']) || !empty($entry['jam_selesai']))
                                                    <span>{{ trim(($entry['jam_mulai'] ?? '') . ($entry['jam_selesai'] ? ' - ' . $entry['jam_selesai'] : '')) }}</span>
                                                @endif
                                                @if(!empty($entry['ruangan']))
                                                    • <span>{{ $entry['ruangan'] }}</span>
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('matkul.edit', $matkul) }}"
                           class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-1.5 font-semibold text-gray-700 hover:border-indigo-200 hover:text-indigo-600">
                            Edit
                        </a>
                        <form action="{{ route('matkul.destroy', $matkul) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    onclick="return confirm('Hapus matkul ini?')"
                                    class="inline-flex items-center rounded-xl border border-rose-200 px-3 py-1.5 font-semibold text-rose-600 hover:bg-rose-50">
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="bg-white border rounded-3xl shadow-sm p-8 text-center space-y-2 sm:col-span-2 xl:col-span-3">
                    <p class="text-lg font-semibold text-gray-900">Belum ada matkul</p>
                    <p class="text-sm text-gray-500">Tambahkan matkul untuk mempercepat pembuatan jadwal dan kegiatan.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
