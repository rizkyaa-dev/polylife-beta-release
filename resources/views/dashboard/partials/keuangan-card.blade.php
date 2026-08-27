        {{-- Kartu: Keuangan Bulan Ini --}}
        <section class="bg-white rounded-2xl shadow-sm border p-4 sm:p-6 min-w-0 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Keuangan Bulan Ini</h2>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Pilih periode untuk melihat ringkasan pemasukan, pengeluaran, dan saldo.</p>
                </div>
                <form method="GET" action="{{ $keuanganFormAction }}" class="flex items-center gap-2 text-sm">
                    @foreach(request()->except('bulan') as $key => $value)
                        @if(is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="bulanKeuanganSelect" class="text-gray-600 dark:text-slate-300">Bulan</label>
                    <div class="relative">
                        <select id="bulanKeuanganSelect"
                                name="bulan"
                                onchange="this.form.submit()"
                                class="appearance-none rounded-xl border border-indigo-100 bg-white/80 pr-10 pl-3 py-2 text-sm font-medium text-gray-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/60 dark:bg-slate-900/70 dark:border-slate-700 dark:text-slate-100 dark:focus:border-indigo-300 dark:focus:ring-indigo-300/60">
                            @foreach($bulanOptions ?? [] as $option)
                                <option value="{{ $option['value'] }}" {{ ($option['value'] ?? '') === ($bulanDipilih ?? now()->format('Y-m')) ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                    </div>
                </form>
            </div>
            <div class="flex flex-col items-center gap-6 mb-6">
                <div class="relative w-full max-w-xs sm:max-w-sm aspect-square bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 rounded-full p-6 sm:p-8 shadow-inner dark:from-slate-800 dark:via-slate-900 dark:to-slate-950">
                    <canvas id="chartKeuanganPie" class="transition-opacity duration-300" style="position: relative; z-index: 2;"></canvas>
                    <div class="absolute inset-[14%] sm:inset-10 flex items-center justify-center pointer-events-none" style="z-index: 4;">
                        <div id="saldoWindow" class="saldo-window relative w-full h-full max-w-[140px] max-h-[140px] rounded-full bg-white shadow-lg overflow-hidden ring-2 ring-indigo-100 dark:bg-slate-950 dark:ring-indigo-900/50">
                            <div id="saldoLiquid" class="saldo-liquid absolute inset-x-0 bottom-0 h-1/2"></div>
                            <div class="saldo-wave saldo-wave-one"></div>
                            <div class="saldo-wave saldo-wave-two"></div>
                            <div class="absolute inset-0 flex items-center justify-center text-center">
                                <p id="saldoIndicatorValue" class="text-2xl font-semibold text-indigo-800 dark:text-indigo-200">0%</p>
                            </div>
                        </div>
                    </div>
                    <div id="chartLoading" class="absolute inset-6 flex items-center justify-center bg-white bg-opacity-90 rounded-full dark:bg-slate-900/90">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                </div>
            </div>

            @php
                $saldoNegatif = ($ringkasanKeuangan['saldo_bulan_ini'] ?? 0) < 0;
            @endphp
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 w-full">
                <button type="button"
                        data-slice="pemasukan"
                        class="stat-card rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow focus-visible:ring-2 focus-visible:ring-green-400 w-full ring-green-300 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:ring-green-400 ring-offset-white dark:ring-offset-slate-900">
                    <div class="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-emerald-300">
                        <span class="inline-flex h-3 w-3 rounded-full" style="background-color: rgba(34, 197, 94, 0.9);"></span>
                        Pemasukan
                    </div>
                    <p id="statPemasukan" data-raw="{{ $ringkasanKeuangan['total_pemasukan'] ?? 0 }}" class="text-2xl font-semibold text-green-800 dark:text-emerald-100 transition">
                        {{ isset($ringkasanKeuangan['total_pemasukan']) ? 'Rp '.number_format($ringkasanKeuangan['total_pemasukan'],0,',','.') : 'Rp 0' }}
                    </p>
                    <p class="text-xs text-green-700/70 dark:text-emerald-200/70 mt-1">Total dana masuk bulan ini</p>
                </button>
                <button type="button"
                        data-slice="pengeluaran"
                        class="stat-card rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow focus-visible:ring-2 focus-visible:ring-rose-400 w-full ring-rose-300 dark:border-rose-500/20 dark:bg-rose-500/10 dark:ring-rose-400 ring-offset-white dark:ring-offset-slate-900">
                    <div class="flex items-center gap-2 text-sm font-medium text-rose-700 dark:text-rose-300">
                        <span class="inline-flex h-3 w-3 rounded-full" style="background-color: rgba(239, 68, 68, 0.9);"></span>
                        Pengeluaran
                    </div>
                    <p id="statPengeluaran" data-raw="{{ $ringkasanKeuangan['total_pengeluaran'] ?? 0 }}" class="text-2xl font-semibold text-rose-800 dark:text-rose-100 transition">
                        {{ isset($ringkasanKeuangan['total_pengeluaran']) ? 'Rp '.number_format($ringkasanKeuangan['total_pengeluaran'],0,',','.') : 'Rp 0' }}
                    </p>
                    <p class="text-xs text-rose-700/70 dark:text-rose-200/70 mt-1">Total dana keluar bulan ini</p>
                </button>
                <button type="button"
                        data-slice="saldo"
                        id="saldoCard"
                        class="stat-card rounded-2xl border px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow focus-visible:ring-2 w-full ring-offset-white dark:ring-offset-slate-900 {{ $saldoNegatif ? 'border-gray-900 bg-gray-900 text-white focus-visible:ring-gray-700 ring-gray-700 dark:ring-gray-500' : 'border-indigo-100 bg-indigo-50 text-indigo-800 focus-visible:ring-indigo-400 ring-indigo-200 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-100 dark:ring-indigo-500' }}">
                    <div id="saldoLabelWrap" class="flex items-center gap-2 text-sm font-medium {{ $saldoNegatif ? 'text-white' : 'text-indigo-700 dark:text-indigo-300' }}">
                        <span class="inline-flex h-3 w-3 rounded-full" style="background-color: rgba(99, 102, 241, 0.9);"></span>
                        <span id="labelSaldo">{{ ($ringkasanKeuangan['saldo_bulan_ini'] ?? 0) < 0 ? 'Hutang' : 'Saldo' }}</span>
                    </div>
                    <p id="statSaldo" data-raw="{{ $ringkasanKeuangan['saldo_bulan_ini'] ?? 0 }}" class="text-2xl font-semibold transition {{ $saldoNegatif ? 'text-white' : 'text-indigo-800 dark:text-indigo-100' }}">
                        {{ isset($ringkasanKeuangan['saldo_bulan_ini']) ? 'Rp '.number_format($ringkasanKeuangan['saldo_bulan_ini'],0,',','.') : 'Rp 0' }}
                    </p>
                    <p class="text-xs mt-1 {{ $saldoNegatif ? 'text-gray-200' : 'text-indigo-700/70 dark:text-indigo-200/70' }}" id="saldoSubtitle">
                        {{ ($ringkasanKeuangan['saldo_bulan_ini'] ?? 0) < 0 ? 'Total hutang per '.now()->format('d M') : 'Sisa dana per '.now()->format('d M') }}
                    </p>
                </button>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                <span class="text-gray-500 dark:text-slate-400">Analisis & Manajemen Keuangan:</span>
                <div class="flex items-center gap-3 font-semibold">
                    <a href="{{ ($guestMode ?? false) ? route('guest.keuangan.anggaran') : route('keuangan.anggaran') }}" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        <span>Plafon Anggaran</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <span class="text-gray-300 dark:text-slate-700">&bull;</span>
                    <a href="{{ ($guestMode ?? false) ? route('guest.keuangan.statistik') : route('keuangan.statistik') }}" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        <span>Statistik & Tren</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>
            </div>
        </section>

