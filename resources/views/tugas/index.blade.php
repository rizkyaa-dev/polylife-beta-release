@extends('layouts.app')

@section('page_title', 'Daftar Tugas')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-wide text-emerald-500 font-semibold">Produktivitas</p>
                    <h2 class="text-2xl font-bold text-gray-900">Tugas Kuliah & Pribadi</h2>
                    <p class="text-sm text-gray-500 mt-1">Kelola tugas berdasarkan deadline dan status.</p>
                </div>
                <a href="{{ route('tugas.create') }}"
                   class="inline-flex items-center rounded-2xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-500">
                    + Tugas Baru
                </a>
            </div>
        </div>

        <div class="bg-white border rounded-3xl shadow-sm p-6">
            @if($tugas->count())
                <div class="space-y-3">
                    @foreach($tugas as $item)
                        @php
                            $deadline = \Illuminate\Support\Carbon::parse($item->deadline);
                            $isDone = $item->status_selesai;
                            $badgeClasses = $isDone ? 'bg-gray-100 text-gray-600' : 'bg-emerald-50 text-emerald-700';
                        @endphp
                        <div class="rounded-2xl border border-gray-100 p-4 shadow-sm flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $item->nama_tugas }}
                                    @if($isDone)
                                        <span class="ml-2 text-xs text-gray-400">(Selesai)</span>
                                    @endif
                                </p>
                                @if($item->deskripsi)
                                    <p class="text-xs text-gray-500 mt-1">{{ $item->deskripsi }}</p>
                                @endif
                                <p class="mt-2 text-xs text-gray-500">Deadline: {{ $deadline->translatedFormat('l, d F Y H:i') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClasses }}">
                                    {{ $isDone ? 'Selesai' : 'Belum selesai' }}
                                </span>
                                <a href="{{ route('tugas.edit', $item) }}"
                                   class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-emerald-200 hover:text-emerald-600">
                                    Edit
                                </a>
                                <form action="{{ route('tugas.destroy', $item) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            onclick="return confirm('Hapus tugas ini?')"
                                            class="inline-flex items-center rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-12 text-center space-y-2">
                    <p class="text-lg font-semibold text-gray-900">Belum ada tugas tersimpan</p>
                    <p class="text-sm text-gray-500">Tambah tugas pertama untuk mulai mengorganisir pekerjaanmu.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
