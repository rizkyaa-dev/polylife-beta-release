{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
    <div class="grid gap-6 xl:grid-cols-2">
        @include('dashboard.partials.jadwal-card')
        @include('dashboard.partials.todo-card')
        @include('dashboard.partials.keuangan-card')
        @include('dashboard.partials.reminder-card')
    </div>
@endsection

@push('styles')
    <style>
        .saldo-window {
            box-shadow: inset 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .saldo-liquid {
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.9) 0%, rgba(59, 130, 246, 0.8) 70%);
            transition: height 0.8s ease, background 0.4s ease;
        }
        .saldo-wave {
            position: absolute;
            left: -25%;
            width: 150%;
            height: 60%;
            background: rgba(255, 255, 255, 0.2);
            opacity: 0.6;
            filter: blur(6px);
            animation: waveMotion 6s linear infinite;
        }
        .saldo-wave-one {
            bottom: 10%;
        }
        .saldo-wave-two {
            bottom: 0;
            animation-duration: 8s;
            animation-direction: reverse;
            opacity: 0.4;
        }
        @keyframes waveMotion {
            0% { transform: translateX(0) rotate(0deg); }
            50% { transform: translateX(10%) rotate(2deg); }
            100% { transform: translateX(0) rotate(0deg); }
        }
        .chart-tooltip {
            position: absolute;
            pointer-events: none;
            z-index: 999;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .reminder-blink {
            animation: reminderBlink 0.9s linear infinite;
        }
        .reminder-blink .reminder-dot {
            animation: reminderDotBlink 0.9s linear infinite;
        }
        @keyframes reminderBlink {
            0%, 100% {
                background-color: #7f1d1d;
                color: #fff;
                border-color: #000;
            }
            50% {
                background-color: #000;
                color: #fca5a5;
                border-color: #7f1d1d;
            }
        }
        @keyframes reminderDotBlink {
            0%, 100% { background-color: #fecaca; }
            50% { background-color: #000; }
        }
    </style>
@endpush

@include('dashboard.partials.reminder-notifications')

@push('scripts')
    {{-- Pie Chart Keuangan yang otomatis update --}}
    <script>
        window.PolyLifeDashboard = {
            reminderEndpoint: @json($reminderDataEndpoint ?? ''),
            keuanganEndpoint: @json($keuanganDataEndpoint ?? ''),
            currentDateLabel: @json(now()->format('d M')),
        };
    </script>
    @vite('resources/js/dashboard.js')
@endpush
