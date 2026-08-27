@extends('layouts.app')

@section('page_title', 'Hapus Jadwal')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 dark:bg-slate-900 dark:border-slate-800">
            <div class="text-center space-y-2">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-rose-50 text-rose-500 dark:bg-rose-500/10 dark:text-rose-400">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Konfirmasi Hapus</h2>
                <p class="text-sm text-gray-500 dark:text-slate-400 max-w-md mx-auto">
                    Apakah anda yakin ingin menghapus agenda ini? Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-800/60 p-4 space-y-2">
                <p class="text-sm font-semibold text-gray-800 dark:text-slate-200">{{ $jadwal->catatan_tambahan ?: ucfirst($jadwal->jenis ?? 'Agenda') }}</p>
                <p class="text-xs text-gray-500 dark:text-slate-400">
                    {{ \Illuminate\Support\Carbon::parse($jadwal->tanggal_mulai)->translatedFormat('d M Y') }}
                    -
                    {{ \Illuminate\Support\Carbon::parse($jadwal->tanggal_selesai)->translatedFormat('d M Y') }}
                </p>
                @if(!empty($jadwal->matkul_names))
                    <p class="text-xs text-gray-500 dark:text-slate-400">
                        Matkul: {{ collect($jadwal->matkul_names)->join(', ') }}
                    </p>
                @endif
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-center">
                <a href="{{ route('jadwal.edit', $jadwal) }}"
                   class="inline-flex items-center justify-center rounded-2xl border border-gray-300 dark:border-slate-700 px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800">
                    Batal
                </a>
                <form action="{{ route('jadwal.destroy', $jadwal) }}" method="POST" class="inline-flex">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-2xl border border-rose-200 dark:border-rose-500/30 bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-1">
                        Ya, hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
