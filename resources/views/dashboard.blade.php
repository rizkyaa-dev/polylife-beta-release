@extends('layouts.app')

@section('page_title', 'PolyLife — Mode Piksel')
@section('page_description', 'Layar super ringkas: pilih cara masuk dan langsung mulai.')

@section('content')
    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-2xl border-4 border-slate-900 bg-violet-100/60 p-6 shadow-[10px_10px_0_0_rgba(15,23,42,0.9)]">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 grid place-items-center rounded-md border-4 border-slate-900 bg-slate-900 text-lilac-100 text-xl font-black shadow-[4px_4px_0_0_rgba(15,23,42,1)]">
                    PL
                </div>
                <div>
                    <p class="text-lg font-black text-slate-900 tracking-wide">PolyLife</p>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-slate-700">Pixel workspace</p>
                </div>
            </div>
            <div class="mt-5 space-y-3">
                <p class="inline-flex items-center rounded-sm border-4 border-slate-900 bg-white px-3 py-1 text-[11px] font-black uppercase tracking-[0.18em] text-slate-900 shadow-[4px_4px_0_0_rgba(15,23,42,0.9)]">
                    Lilac calm mode
                </p>
                <h1 class="text-3xl font-black leading-tight text-slate-900">Satu layar minimalis. Semua di tempatnya.</h1>
                <p class="text-sm text-slate-800 max-w-xl">
                    Tidak ada widget tambahan. Hanya tombol penting untuk masuk, daftar, atau coba sebagai tamu dalam gaya piksel.
                </p>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('workspace.home') }}"
                            class="px-5 py-3 rounded-sm border-4 border-slate-900 bg-violet-200 text-slate-900 text-sm font-black shadow-[6px_6px_0_0_rgba(15,23,42,0.9)] hover:-translate-y-1 transition">
                            Kembali ke dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="px-5 py-3 rounded-sm border-4 border-slate-900 bg-violet-200 text-slate-900 text-sm font-black shadow-[6px_6px_0_0_rgba(15,23,42,0.9)] hover:-translate-y-1 transition">
                            Login
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                                class="px-5 py-3 rounded-sm border-4 border-slate-900 bg-white text-slate-900 text-sm font-black shadow-[6px_6px_0_0_rgba(15,23,42,0.9)] hover:-translate-y-1 transition">
                                Register
                            </a>
                        @endif
                    @endauth
                @endif
                <a href="{{ route('guest.home') }}"
                    class="px-5 py-3 rounded-sm border-4 border-slate-900 bg-slate-900 text-violet-100 text-sm font-black shadow-[6px_6px_0_0_rgba(15,23,42,0.9)] hover:-translate-y-1 transition">
                    Mode tamu
                </a>
            </div>
        </div>

        <div class="rounded-2xl border-4 border-slate-900 bg-white p-6 shadow-[10px_10px_0_0_rgba(15,23,42,0.9)]">
            <div class="space-y-3">
                <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500 font-black">Kenapa minimal</p>
                <p class="text-lg font-black text-slate-900">Tanpa panel tambahan, tanpa kebisingan.</p>
                <p class="text-sm text-slate-700">
                    Fokus ke aksi utama saja. Tombol terlihat jelas, warna lilac jadi aksen utama, dan tipografi tebal ala piksel membuat setiap klik terasa tegas.
                </p>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-sm border-4 border-slate-900 bg-violet-100/70 p-3 shadow-[4px_4px_0_0_rgba(15,23,42,0.9)]">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-900">Arahkan</p>
                    <p class="text-sm text-slate-800">Login atau register untuk data pribadi.</p>
                </div>
                <div class="rounded-sm border-4 border-slate-900 bg-white p-3 shadow-[4px_4px_0_0_rgba(15,23,42,0.9)]">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-900">Coba cepat</p>
                    <p class="text-sm text-slate-800">Mode tamu untuk melihat tata letak.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
