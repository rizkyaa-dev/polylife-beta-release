@props([
    'activeTab' => 'transaksi',
    'guestMode' => false,
])

@php
    $tabs = [
        'transaksi' => [
            'label' => 'Buku Kas',
            'url' => $guestMode ? route('guest.keuangan.index') : route('keuangan.index'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />',
        ],
        'statistik' => [
            'label' => 'Statistik & Tren',
            'url' => $guestMode ? route('guest.keuangan.statistik') : route('keuangan.statistik'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />',
        ],
        'anggaran' => [
            'label' => 'Plafon Anggaran',
            'url' => $guestMode ? route('guest.keuangan.anggaran') : route('keuangan.anggaran'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        ],
    ];
@endphp

<div class="flex items-center gap-1.5 p-1.5 bg-gray-100/90 dark:bg-slate-800/90 rounded-2xl border border-gray-200/70 dark:border-slate-700/70 overflow-x-auto">
    @foreach($tabs as $key => $tab)
        @php
            $isActive = $activeTab === $key;
        @endphp
        <a href="{{ $tab['url'] }}"
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-150 {{ $isActive ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm border border-gray-200/60 dark:border-slate-700' : 'text-gray-600 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-700/50' }}">
            <svg class="w-4 h-4 {{ $isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                {!! $tab['icon'] !!}
            </svg>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</div>
