@extends('layouts.app')

@section('page_title', 'Statistik Keuangan (Beta)')

@section('content')
@php
    $guestMode = $guestMode ?? false;
    $statistikAction = $guestMode ? route('guest.keuangan.statistik') : route('keuangan.statistik');
@endphp
<div class="space-y-6">
    <div class="bg-white border rounded-2xl shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Ringkasan Tahunan</h2>
                <p class="text-gray-500">Analisis canggih keuangan tahun <span class="font-medium">{{ $tahun }}</span></p>
            </div>
            <form method="GET" action="{{ $statistikAction }}" class="flex items-center gap-2">
                <label for="tahun" class="text-sm text-gray-600">Tahun</label>
                <select id="tahun" name="tahun" class="px-3 py-2 rounded-lg bg-gray-50 border">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
                <button class="px-3 py-2 rounded-lg bg-gray-800 text-white hover:bg-black">Terapkan</button>
                <button type="button" onclick="window.print()" class="px-3 py-2 rounded-lg bg-rose-500 text-white hover:bg-rose-600">
                    Export PDF
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="rounded-xl p-4 bg-green-50">
                <p class="text-sm text-green-700">Total Pemasukan</p>
                <p class="text-2xl font-bold text-green-900">Rp {{ number_format($totalPemasukan,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-rose-50">
                <p class="text-sm text-rose-700">Total Pengeluaran</p>
                <p class="text-2xl font-bold text-rose-900">Rp {{ number_format($totalPengeluaran,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-indigo-50">
                <p class="text-sm text-indigo-700">Saldo (Netto)</p>
                <p class="text-2xl font-bold text-indigo-900">Rp {{ number_format($totalNet,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-amber-50">
                <p class="text-sm text-amber-700">Savings Rate</p>
                <p class="text-2xl font-bold text-amber-900">{{ number_format($savingsRate * 100, 1, ',', '.') }}%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div class="rounded-xl p-4 bg-gray-50">
                <p class="text-sm text-gray-700">Rata-rata Pemasukan / bulan</p>
                <p class="text-xl font-semibold">Rp {{ number_format($avgPemasukan,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gray-50">
                <p class="text-sm text-gray-700">Rata-rata Pengeluaran / bulan</p>
                <p class="text-xl font-semibold">Rp {{ number_format($avgPengeluaran,0,',','.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gray-50">
                <p class="text-sm text-gray-700">Proyeksi Akhir Tahun</p>
                <p class="text-xl font-semibold">Rp {{ number_format($proyeksiAkhirTahun,0,',','.') }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white border rounded-2xl shadow-sm p-6">
        <h3 class="text-lg font-semibold mb-4">Tren Bulanan</h3>
        <canvas id="chartKeuangan" height="120"></canvas>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white border rounded-2xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Top Kategori Pengeluaran</h3>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2 pr-4">Kategori</th>
                        <th class="py-2 pr-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($topKategoriPengeluaran as $kat => $val)
                    <tr>
                        <td class="py-2 pr-4">{{ $kat }}</td>
                        <td class="py-2 pr-4 text-right">Rp {{ number_format($val,0,',','.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="py-4 text-center text-gray-500">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-white border rounded-2xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Top Kategori Pemasukan</h3>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2 pr-4">Kategori</th>
                        <th class="py-2 pr-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($topKategoriPemasukan as $kat => $val)
                    <tr>
                        <td class="py-2 pr-4">{{ $kat }}</td>
                        <td class="py-2 pr-4 text-right">Rp {{ number_format($val,0,',','.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="py-4 text-center text-gray-500">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Deteksi Anomali Pengeluaran</h3>
            <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-800">Algoritma: mean + 1.5σ</span>
        </div>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left border-b">
                    <th class="py-2 pr-4">Bulan</th>
                    <th class="py-2 pr-4 text-right">Pengeluaran</th>
                    <th class="py-2 pr-4 text-right">Batas</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($anomali as $a)
                <tr>
                    <td class="py-2 pr-4">{{ $a['bulan'] }}</td>
                    <td class="py-2 pr-4 text-right">Rp {{ number_format($a['nilai'],0,',','.') }}</td>
                    <td class="py-2 pr-4 text-right">Rp {{ number_format($a['batas'],0,',','.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="py-4 text-center text-gray-500">Tidak ada anomali terdeteksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(!empty($saran))
    <div class="bg-indigo-50 border rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-2">Saran Otomatis</h3>
        <ul class="list-disc pl-5 space-y-1 text-indigo-900">
            @foreach($saran as $s)
                <li>{{ $s }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const labels = @json($labels);
    const pemasukan = @json($seriesPemasukan);
    const pengeluaran = @json($seriesPengeluaran);
    const net = @json($seriesNet);
    const saldo = @json($cumulativeSaldo);

    const ctx = document.getElementById('chartKeuangan');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Pemasukan', data: pemasukan, backgroundColor: '#10b981' },
                    { label: 'Pengeluaran', data: pengeluaran, backgroundColor: '#ef4444' },
                    { type: 'line', label: 'Netto', data: net, borderColor: '#4f46e5', backgroundColor: 'transparent', yAxisID: 'y' },
                    { type: 'line', label: 'Saldo Kumulatif', data: saldo, borderColor: '#f59e0b', backgroundColor: 'transparent', yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                stacked: false,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Nominal (Rp)' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Saldo' } }
                }
            }
        });
    }
</script>
<style>
/* Print friendly */
@media print {
    header, nav, aside, [role="button"], button, form[action*="statistik"] button[type="submit"], .no-print { display: none !important; }
    main { padding: 0 !important; }
    .border, .shadow-sm, .rounded-2xl { box-shadow: none !important; }
    .bg-white { background: #fff !important; }
    @page { size: A4; margin: 12mm; }
}
</style>
@endpush
@endsection
