@extends('errors.layout')

@php
    $exceptionHeaders = isset($exception) && method_exists($exception, 'getHeaders')
        ? $exception->getHeaders()
        : [];
    $retryAfter = max(0, (int) ($retryAfter ?? ($exceptionHeaders['Retry-After'] ?? 0)));
    $waitText = $retryAfter > 0 ? $retryAfter.' detik' : 'beberapa saat';
    $retryAfterMarkup = $retryAfter > 0
        ? '<span data-error-countdown data-error-countdown-seconds="'.$retryAfter.'" data-error-countdown-ready="sekarang">'.$retryAfter.' detik</span>'
        : 'beberapa saat';
@endphp

@section('code', '429')
@section('title', 'Terlalu banyak aksi dalam waktu singkat')
@section('message')
    {!! e($customMessage ?? 'Sistem menahan sementara permintaan berulang untuk menjaga performa dan mencegah exploit.').' Coba lagi dalam '.$retryAfterMarkup.'.' !!}
@endsection
@section('details')
    <div class="rounded-[20px] border-2 border-[#2B2250]/15 bg-white/85 p-4 shadow-[6px_6px_0_0_rgba(197,212,255,0.75)] dark:border-white/10 dark:bg-[#130F29]/85 dark:shadow-[6px_6px_0_0_rgba(8,6,18,0.75)]">
        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#6D6797] dark:text-[#B6B0EC]">Yang Terjadi</p>
        <p class="mt-2 text-sm leading-6 text-[#4A4567] dark:text-[#D7D2F5]">
            Sistem mendeteksi permintaan tulis yang terlalu cepat atau identik dalam jeda singkat.
        </p>
    </div>
    <div class="rounded-[20px] border-2 border-[#2B2250]/15 bg-white/85 p-4 shadow-[6px_6px_0_0_rgba(197,212,255,0.75)] dark:border-white/10 dark:bg-[#130F29]/85 dark:shadow-[6px_6px_0_0_rgba(8,6,18,0.75)]">
        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#6D6797] dark:text-[#B6B0EC]">Saran</p>
        <p class="mt-2 text-sm leading-6 text-[#4A4567] dark:text-[#D7D2F5]">
            Hindari klik tombol simpan berulang, lalu ulangi aksi setelah
            @if ($retryAfter > 0)
                <span data-error-countdown data-error-countdown-seconds="{{ $retryAfter }}" data-error-countdown-ready="sekarang">{{ $waitText }}</span>.
            @else
                {{ $waitText }}.
            @endif
        </p>
    </div>
@endsection
