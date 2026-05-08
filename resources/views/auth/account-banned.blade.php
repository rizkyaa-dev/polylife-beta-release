<x-guest-layout>
    <div class="space-y-8">
        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 rounded-[14px] border-2 border-rose-300/40 bg-rose-50 px-4 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-rose-700 dark:border-rose-300/30 dark:bg-rose-500/10 dark:text-rose-200">
                <span class="h-2 w-2 bg-rose-500 dark:bg-rose-300"></span>
                Akun Diblokir
            </div>
            <h1 class="text-3xl font-bold leading-tight text-[#2D2D3C] dark:text-white">
                Akses akun PolyLife sedang dibatasi
            </h1>
            <p class="text-base leading-7 text-[#6D6797] dark:text-[#C7C2EE]">
                Akun dengan email <span class="font-semibold text-[#2D2D3C] dark:text-white">{{ $user->email }}</span> tidak dapat mengakses workspace untuk sementara.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-[20px] border-2 border-[#2B2250]/15 bg-white/85 p-4 shadow-[6px_6px_0_0_rgba(197,212,255,0.75)] dark:border-white/10 dark:bg-[#130F29]/85 dark:shadow-[6px_6px_0_0_rgba(8,6,18,0.75)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#6D6797] dark:text-[#B6B0EC]">Alasan</p>
                <p class="mt-2 text-sm font-semibold leading-6 text-[#2D2D3C] dark:text-white">
                    {{ $reasonLabel ?: 'Tidak ada kode alasan yang dicantumkan.' }}
                </p>
                @if ($reasonCode)
                    <p class="mt-1 text-xs text-[#6D6797] dark:text-[#B6B0EC]">
                        Kode: {{ $reasonCode }}
                    </p>
                @endif
            </div>

            <div class="rounded-[20px] border-2 border-[#2B2250]/15 bg-white/85 p-4 shadow-[6px_6px_0_0_rgba(197,212,255,0.75)] dark:border-white/10 dark:bg-[#130F29]/85 dark:shadow-[6px_6px_0_0_rgba(8,6,18,0.75)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#6D6797] dark:text-[#B6B0EC]">Waktu Pemblokiran</p>
                <p class="mt-2 text-sm font-semibold leading-6 text-[#2D2D3C] dark:text-white">
                    {{ $bannedAt ? $bannedAt->format('Y-m-d H:i') : 'Tidak tercatat' }}
                </p>
                <p class="mt-1 text-xs text-[#6D6797] dark:text-[#B6B0EC]">
                    Waktu mengikuti konfigurasi server aplikasi.
                </p>
            </div>
        </div>

        <div class="rounded-[20px] border-2 border-rose-300/30 bg-rose-50/80 p-4 shadow-[6px_6px_0_0_rgba(254,205,211,0.75)] dark:border-rose-300/20 dark:bg-rose-500/10 dark:shadow-[6px_6px_0_0_rgba(8,6,18,0.75)]">
            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-rose-700 dark:text-rose-200">Catatan Super Admin</p>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-rose-900 dark:text-rose-100">{{ $reasonNote !== '' ? $reasonNote : 'Tidak ada catatan tambahan.' }}</p>
        </div>

        <div class="rounded-[18px] border-2 border-[#2B2250]/10 bg-white/70 p-4 text-sm leading-6 text-[#4A4567] dark:border-white/10 dark:bg-[#0F0A21]/70 dark:text-[#D7D2F5]">
            Jika kamu merasa pemblokiran ini keliru, hubungi super admin dan sertakan email akun yang tertera di halaman ini. Detail internal lain tidak ditampilkan untuk menjaga keamanan akun.
        </div>

        <form method="POST" action="{{ route('logout') }}" class="pt-1">
            @csrf
            <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-[18px] border-2 border-[#2B2250] bg-[#8181FF] px-4 py-3 text-base font-semibold uppercase tracking-wide text-white shadow-[5px_5px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 hover:shadow-[7px_7px_0_0_#2B2250] focus:outline-none focus:ring-2 focus:ring-[#F9A8D4] dark:border-[#0B0718] dark:bg-[#6A5BFF] dark:shadow-[5px_5px_0_0_rgba(5,3,12,0.9)] dark:focus:ring-[#F49CC8]/60">
                Keluar
            </button>
        </form>
    </div>
</x-guest-layout>
