@php
    $retryAfter = $retryAfter ?? null;
    $message = $message ?? 'Pendaftaran dibatasi sementara. Silakan coba lagi nanti.';
@endphp

<div id="registerRateLimitModal"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-8">
    <div class="relative max-w-md w-full rounded-2xl border-4 border-[#2B2250] bg-white p-6 shadow-[12px_12px_0_0_#2B2250] dark:border-[#0B0718] dark:bg-[#0F0B1F] dark:shadow-[12px_12px_0_0_rgba(5,3,12,0.9)]">
        <div class="absolute -top-4 right-6 inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-500/20 dark:text-amber-200">
            Batas daftar
        </div>
        <div class="space-y-3">
            <div>
                <h3 class="text-xl font-bold text-[#2D2D3C] dark:text-white">Pelan dulu, ya</h3>
                <p class="text-sm text-[#565670] dark:text-[#CBC7EA]">
                    {{ $message }}
                    @if($retryAfter)
                        Coba lagi dalam <span class="font-semibold">{{ $retryAfter }} detik</span>.
                    @endif
                </p>
            </div>
            <div class="rounded-xl bg-[#F7F4FF] p-3 text-sm text-[#45445F] dark:bg-[#18122E] dark:text-[#E8E4FF]">
                Satu akun bisa dibuat setiap 1 menit untuk pengguna tamu. Jika butuh bantuan, hubungi admin kampus.
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button"
                        data-close-register-limit
                        class="inline-flex items-center gap-2 rounded-xl border-2 border-[#2B2250] bg-[#B5F1FF] px-4 py-2 text-sm font-semibold text-[#1F1E3F] shadow-[5px_5px_0_0_#2B2250] transition hover:-translate-y-0.5 hover:-translate-x-0.5 dark:border-[#0B0718] dark:bg-[#6A5BFF]/70 dark:text-white dark:shadow-[5px_5px_0_0_rgba(5,3,12,0.9)]">
                    Oke, mengerti
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('registerRateLimitModal');
        if (!modal) return;
        const close = () => modal.remove();
        modal.querySelector('[data-close-register-limit]')?.addEventListener('click', close);
        const onEscape = (event) => {
            if (event.key === 'Escape') {
                close();
            }
        };
        document.addEventListener('keydown', onEscape, { once: true });
    })();
</script>
