<?php

namespace App\Services\Ai;

final class AiRunErrorPresenter
{
    public function message(?string $code): string
    {
        return match ($code) {
            'provider_timeout' => 'Jawaban AI membutuhkan waktu terlalu lama. Coba lagi atau turunkan Thinking effort.',
            'provider_rate_limited' => 'Penyedia AI sedang sibuk. Tunggu sebentar lalu coba lagi.',
            'provider_unavailable' => 'Penyedia AI sementara tidak tersedia. Pesanmu aman dan bisa dicoba lagi.',
            'provider_response_truncated' => 'Jawaban AI terpotong sebelum selesai. Pesanmu aman dan bisa dicoba lagi.',
            'provider_authentication', 'provider_request_invalid' => 'Layanan AI belum dapat memproses permintaan ini. Hubungi pengelola aplikasi.',
            'run_lease_expired' => 'Proses sebelumnya terhenti sebelum selesai. Pesanmu aman dan bisa dicoba lagi.',
            'worker_failed' => 'Proses AI terhenti di server. Pesanmu aman dan bisa dicoba lagi.',
            'queue_dispatch_failed' => 'Pesan belum dapat dimasukkan ke antrean AI. Silakan coba lagi.',
            'user_cancelled' => 'Proses AI dihentikan.',
            default => 'Terjadi kendala teknis saat memproses pesan AI. Pesanmu aman dan bisa dicoba lagi.',
        };
    }
}
