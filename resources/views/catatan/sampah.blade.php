@extends('layouts.app')

@section('page_title', 'Sampah Catatan')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500">Catatan yang dipindahkan ke sampah akan tersimpan sementara</p>
                <h2 class="text-2xl font-semibold text-gray-900">Sampah Catatan</h2>
            </div>
            <a href="{{ route('catatan.index') }}"
               class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                &larr; Kembali ke daftar catatan
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($catatans->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center text-gray-500">
                Sampah kosong. Catatan yang kamu hapus sementara akan muncul di sini.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($catatans as $catatan)
                    <article class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Judul</p>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $catatan->judul }}</h3>
                                <p class="mt-1 text-xs text-gray-500">
                                    Dihapus {{ $catatan->updated_at?->diffForHumans() ?? '-' }} |
                                    Tanggal catatan: {{ \Illuminate\Support\Carbon::parse($catatan->tanggal)->translatedFormat('d M Y') }}
                                </p>
                                <p class="mt-3 text-sm text-gray-600 line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags($catatan->isi), 200) }}</p>
                            </div>
                            <div class="flex flex-col gap-2 text-sm">
                                <form action="{{ route('catatan.restore', $catatan) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 px-4 py-2 font-semibold text-emerald-600 hover:bg-emerald-50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Pulihkan
                                    </button>
                                </form>
                                <form action="{{ route('catatan.force-delete', $catatan) }}" method="POST" onsubmit="return confirm('Hapus permanen catatan ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2 font-semibold text-rose-600 hover:bg-rose-50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Hapus Permanen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
