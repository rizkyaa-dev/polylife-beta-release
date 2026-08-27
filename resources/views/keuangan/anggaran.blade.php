@extends('layouts.app')

@section('page_title', 'Plafon Anggaran Keuangan')

@section('content')
@php
    $guestMode = $guestMode ?? false;
    $anggaranAction = $guestMode ? route('guest.keuangan.anggaran') : route('keuangan.anggaran');
    $rawNominal = old('nominal_limit');
    $nominalDisplay = $rawNominal ? number_format((float) $rawNominal, 0, ',', '.') : '';
    $nominalValue = $rawNominal ?: '';
@endphp

<div class="space-y-6">
    {{-- Header & Sub-Tabs Navigation --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Modul Keuangan</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400">Kelola arus kas, analisis tahunan, dan kontrol batas pengeluaran pos anggaran.</p>
        </div>
        <div class="w-full md:w-auto">
            @include('keuangan.partials.nav-tabs', ['activeTab' => 'anggaran', 'guestMode' => $guestMode])
        </div>
    </div>

    {{-- Notification Messages --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filter Period & Summary Card --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-slate-100">Plafon Anggaran Periode</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">Monitoring pengeluaran per pos kategori untuk <span class="font-semibold text-gray-800 dark:text-slate-200">{{ $month_name }} {{ $year }}</span></p>
            </div>
            <form method="GET" action="{{ $anggaranAction }}" class="flex flex-wrap items-center gap-2">
                <select name="bulan" class="px-3 py-2 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    @foreach($monthOptions as $mNum => $mLabel)
                        <option value="{{ $mNum }}" {{ $currentMonth == $mNum ? 'selected' : '' }}>{{ $mLabel }}</option>
                    @endforeach
                </select>
                <select name="tahun" class="px-3 py-2 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    @foreach($tahunOptions as $tOption)
                        <option value="{{ $tOption }}" {{ $currentYear == $tOption ? 'selected' : '' }}>{{ $tOption }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-500 shadow-sm">
                    Terapkan
                </button>
            </form>
        </div>

        {{-- Metrics Summary Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <div class="rounded-xl p-4 bg-indigo-50/80 border border-indigo-100 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase">Total Plafon Anggaran</p>
                <p class="mt-2 text-2xl font-bold text-indigo-950 dark:text-indigo-100">Rp {{ number_format($total_limit, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl p-4 bg-gray-50 border border-gray-100 dark:bg-slate-800/80 dark:border-slate-700">
                <p class="text-xs font-semibold text-gray-600 dark:text-slate-400 uppercase">Realisasi Belanja</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-slate-100">Rp {{ number_format($total_spent, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">{{ $overall_percentage }}% dari plafon</p>
            </div>
            <div class="rounded-xl p-4 {{ $total_remaining > 0 ? 'bg-emerald-50/80 border border-emerald-100 dark:border-emerald-500/20 dark:bg-emerald-500/10' : 'bg-rose-50 border border-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10' }}">
                <p class="text-xs font-semibold {{ $total_remaining > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }} uppercase">
                    {{ $total_remaining > 0 ? 'Sisa Anggaran' : 'Kelebihan (Overbudget)' }}
                </p>
                <p class="mt-2 text-2xl font-bold {{ $total_remaining > 0 ? 'text-emerald-950 dark:text-emerald-100' : 'text-rose-950 dark:text-rose-100' }}">
                    Rp {{ number_format($total_remaining > 0 ? $total_remaining : $total_overbudget, 0, ',', '.') }}
                </p>
            </div>
            <div class="rounded-xl p-4 {{ $health_color === 'emerald' ? 'bg-emerald-50 border border-emerald-100 dark:border-emerald-500/20 dark:bg-emerald-500/10' : ($health_color === 'amber' ? 'bg-amber-50 border border-amber-100 dark:border-amber-500/20 dark:bg-amber-500/10' : 'bg-rose-50 border border-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10') }}">
                <p class="text-xs font-semibold {{ $health_color === 'emerald' ? 'text-emerald-700 dark:text-emerald-300' : ($health_color === 'amber' ? 'text-amber-700 dark:text-amber-300' : 'text-rose-700 dark:text-rose-300') }} uppercase">Status Anggaran</p>
                <p class="mt-2 text-2xl font-bold {{ $health_color === 'emerald' ? 'text-emerald-900 dark:text-emerald-100' : ($health_color === 'amber' ? 'text-amber-900 dark:text-amber-100' : 'text-rose-900 dark:text-rose-100') }}">{{ $health_status }}</p>
                <p class="text-xs {{ $health_color === 'emerald' ? 'text-emerald-600 dark:text-emerald-400' : ($health_color === 'amber' ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }} mt-1">
                    {{ $overbudget_count > 0 ? $overbudget_count . ' kategori melebihi batas' : 'Semua pos dalam batas aman' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Main Content Grid: Budget Progress Items & Add Budget Form --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2 Columns: Budget Items List --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">Daftar Plafon Pos Anggaran</h3>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Pantau batas maksimal pengeluaran per pos setiap bulan.</p>
                    </div>
                </div>

                @if(empty($items))
                    <div class="rounded-2xl border border-dashed border-gray-200 dark:border-slate-700 p-8 text-center">
                        <div class="mx-auto w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <h4 class="text-base font-semibold text-gray-800 dark:text-slate-200">Belum Ada Plafon Anggaran</h4>
                        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                            Tentukan batas anggaran untuk pos pengeluaran Anda di bulan {{ $month_name }} {{ $year }} menggunakan formulir di samping.
                        </p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($items as $item)
                            @php
                                $statusColor = match($item['status']) {
                                    'over' => 'rose',
                                    'waspada' => 'amber',
                                    default => 'emerald',
                                };
                                $barWidth = min(100, $item['percentage']);
                            @endphp
                            <div class="rounded-2xl border border-gray-100 dark:border-slate-800 p-5 bg-gray-50/50 dark:bg-slate-800/40 hover:border-indigo-100 dark:hover:border-slate-700 transition-all">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-3">
                                        <span class="w-3 h-3 rounded-full {{ $item['status'] === 'over' ? 'bg-rose-500 animate-pulse' : ($item['status'] === 'waspada' ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                                        <div>
                                            <h4 class="text-base font-bold text-gray-900 dark:text-slate-100">{{ $item['kategori'] }}</h4>
                                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                                Terpakai: <span class="font-semibold text-gray-800 dark:text-slate-200">Rp {{ number_format($item['actual_spent'], 0, ',', '.') }}</span> dari <span class="font-semibold text-gray-800 dark:text-slate-200">Rp {{ number_format($item['nominal_limit'], 0, ',', '.') }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right flex items-center gap-2">
                                        <div class="mr-1">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $item['status'] === 'over' ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' : ($item['status'] === 'waspada' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300') }}">
                                                {{ $item['percentage'] }}%
                                            </span>
                                            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                                                {{ $item['status'] === 'over' ? 'Over Rp ' . number_format($item['overbudget'], 0, ',', '.') : 'Sisa Rp ' . number_format($item['remaining'], 0, ',', '.') }}
                                            </p>
                                        </div>

                                        @unless($guestMode)
                                            <button type="button"
                                                    onclick="populateBudgetForm('{{ addslashes($item['kategori']) }}', '{{ (int)$item['nominal_limit'] }}');"
                                                    class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors"
                                                    title="Ubah Plafon Anggaran">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            @if($item['id'])
                                                <form action="{{ route('keuangan.anggaran.destroy', $item['id']) }}" method="POST" onsubmit="return confirm('Hapus plafon anggaran untuk kategori {{ $item['kategori'] }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors" title="Hapus Anggaran">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        @endunless
                                    </div>
                                </div>

                                {{-- Visual Progress Bar --}}
                                <div class="mt-3 w-full bg-gray-200 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-300 {{ $item['status'] === 'over' ? 'bg-rose-500' : ($item['status'] === 'waspada' ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                         style="width: {{ $barWidth }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Unbudgeted Expense Alerts --}}
            @if(!empty($unbudgeted_expenses))
                <div class="bg-amber-50/70 border border-amber-200/80 rounded-2xl p-5 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200 mb-2">Pos Pengeluaran Belum Beranggaran:</h4>
                    <p class="text-xs text-amber-800 dark:text-amber-300 mb-3">Terdapat transaksi pengeluaran di kategori berikut yang belum memiliki batas plafon:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($unbudgeted_expenses as $unb)
                            <button type="button"
                                    onclick="populateBudgetForm('{{ addslashes($unb['kategori']) }}', '{{ (int)$unb['actual_spent'] }}');"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-amber-200 dark:border-slate-700 text-xs font-semibold text-gray-700 dark:text-slate-200 hover:border-indigo-300 hover:text-indigo-600 transition-all shadow-sm">
                                <span>{{ $unb['kategori'] }} (Rp {{ number_format($unb['actual_spent'], 0, ',', '.') }})</span>
                                <span class="text-indigo-600 dark:text-indigo-400 font-bold">+ Pasang</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Right Column: Set Budget Form --}}
        <div class="space-y-6">
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
                <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100 mb-1">+ Pasang Plafon Anggaran</h3>
                <p class="text-xs text-gray-500 dark:text-slate-400 mb-5">Atur atau perbarui batas maksimal pengeluaran per kategori.</p>

                @if($guestMode)
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-4 text-xs text-indigo-900 dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-300">
                        Mode Tamu: Anda dapat melihat simulasi data anggaran. Untuk menyimpan anggaran pribadi, silakan login ke akun Anda.
                    </div>
                @else
                    <form action="{{ route('keuangan.anggaran.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="bulan" value="{{ $currentMonth }}">
                        <input type="hidden" name="tahun" value="{{ $currentYear }}">

                        <div>
                            <label for="form_kategori" class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1">Kategori Pos Belanja</label>
                            <input list="category_list" id="form_kategori" name="kategori" required
                                   value="{{ old('kategori') }}"
                                   placeholder="Pilih atau ketik kategori..."
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-800 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <datalist id="category_list">
                                @foreach($category_suggestions as $sug)
                                    <option value="{{ $sug }}"></option>
                                @endforeach
                            </datalist>
                            @error('kategori')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="nominal_limit_display" class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1">Plafon Maksimal</label>
                            <div class="relative rounded-xl shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                    <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Rp</span>
                                </div>
                                <input type="text"
                                       id="nominal_limit_display"
                                       inputmode="numeric"
                                       value="{{ $nominalDisplay }}"
                                       placeholder="0"
                                       class="w-full pl-11 pr-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-800 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-semibold"
                                       required>
                                <input type="hidden"
                                       name="nominal_limit"
                                       id="nominal_limit"
                                       value="{{ $nominalValue }}">
                            </div>
                            @error('nominal_limit')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-1">Periode: {{ $month_name }} {{ $year }} &bull; Arrow Up/Down untuk ubah nominal</p>
                        </div>

                        <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-500 shadow-md transition-all">
                            Simpan Plafon Anggaran
                        </button>
                    </form>
                @endif
            </div>

            {{-- Tips Card --}}
            <div class="bg-indigo-50/70 border border-indigo-100 rounded-2xl p-5 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                <h4 class="text-sm font-bold text-indigo-900 dark:text-indigo-200 mb-2">Tips Smart Budgeting</h4>
                <ul class="text-xs text-indigo-800 dark:text-indigo-300 space-y-1.5 list-disc pl-4">
                    <li>Gunakan aturan <strong>50/30/20</strong>: 50% kebutuhan pokok, 30% makan & pribadi, 20% tabungan.</li>
                    <li>Gunakan tombol panah <strong>Up / Down</strong> pada input nominal untuk mengatur nominal dengan cepat sesuai digit kursor.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.populateBudgetForm = (kategori, nominal) => {
        const katInput = document.getElementById('form_kategori');
        const displayInput = document.getElementById('nominal_limit_display');
        const hiddenInput = document.getElementById('nominal_limit');

        if (katInput) {
            katInput.value = kategori;
        }

        if (nominal && displayInput && hiddenInput) {
            const digits = String(nominal).replace(/\D/g, '');
            hiddenInput.value = digits;
            try {
                displayInput.value = new Intl.NumberFormat('id-ID').format(BigInt(digits));
            } catch (e) {
                displayInput.value = digits;
            }
            displayInput.focus();
        } else if (displayInput) {
            displayInput.focus();
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const displayInput = document.getElementById('nominal_limit_display');
        const hiddenInput = document.getElementById('nominal_limit');

        if (!displayInput || !hiddenInput) return;

        const formatRupiah = (raw) => {
            const digits = String(raw ?? '').replace(/\D/g, '');
            if (!digits) return { formatted: '', raw: '' };
            try {
                const formatted = new Intl.NumberFormat('id-ID').format(BigInt(digits));
                return { formatted, raw: digits };
            } catch (e) {
                return { formatted: digits, raw: digits };
            }
        };

        const handleInput = () => {
            const val = displayInput.value;
            const cursorPos = displayInput.selectionStart || 0;
            const digitsBeforeCursor = (val.slice(0, cursorPos).match(/\d/g) || []).length;

            const { formatted, raw } = formatRupiah(val);
            displayInput.value = formatted;
            hiddenInput.value = raw;

            if (cursorPos !== null) {
                let newCursorPos = 0;
                let digitsCounted = 0;
                for (let i = 0; i < formatted.length; i++) {
                    if (/\d/.test(formatted[i])) {
                        digitsCounted++;
                    }
                    if (digitsCounted === digitsBeforeCursor) {
                        newCursorPos = i + 1;
                        break;
                    }
                }
                if (digitsBeforeCursor === 0) newCursorPos = 0;
                if (digitsCounted < digitsBeforeCursor) newCursorPos = formatted.length;
                displayInput.setSelectionRange(newCursorPos, newCursorPos);
            }
        };

        const handleKeyDown = (e) => {
            if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;

            e.preventDefault();

            const val = displayInput.value;
            const cursorPos = displayInput.selectionStart ?? val.length;

            const textBefore = val.slice(0, cursorPos);
            const textAfter = val.slice(cursorPos);

            const digitsBefore = (textBefore.match(/\d/g) || []).length;
            let digitsAfter = (textAfter.match(/\d/g) || []).length;
            const totalDigits = digitsBefore + digitsAfter;

            if (totalDigits === 0) {
                if (e.key === 'ArrowUp') {
                    displayInput.value = '1.000';
                    hiddenInput.value = '1000';
                    displayInput.setSelectionRange(1, 1);
                }
                return;
            }

            let power = digitsAfter;
            if (digitsBefore === 0 && totalDigits > 0) {
                power = totalDigits - 1;
                digitsAfter = totalDigits - 1;
            }

            const step = 10n ** BigInt(power);
            const currentRaw = val.replace(/\D/g, '') || '0';
            let currentNum = BigInt(currentRaw);

            if (e.key === 'ArrowUp') {
                currentNum += step;
            } else if (e.key === 'ArrowDown') {
                if (currentNum >= step) {
                    currentNum -= step;
                } else {
                    currentNum = 0n;
                }
            }

            const newDigits = currentNum.toString();
            const formatted = new Intl.NumberFormat('id-ID').format(currentNum);

            displayInput.value = formatted;
            hiddenInput.value = newDigits;

            let targetDigitsToRight = digitsAfter;
            let newCursorPos = formatted.length;
            let countFromRight = 0;

            for (let i = formatted.length - 1; i >= 0; i--) {
                if (/\d/.test(formatted[i])) {
                    countFromRight++;
                    if (countFromRight === targetDigitsToRight) {
                        newCursorPos = i;
                        if (newCursorPos > 0 && formatted[newCursorPos - 1] === '.') {
                            newCursorPos -= 1;
                        }
                        break;
                    }
                }
            }

            if (targetDigitsToRight === 0) {
                newCursorPos = formatted.length;
            }

            if (digitsBefore === 0 && countFromRight === 0) {
                newCursorPos = 0;
            }

            displayInput.setSelectionRange(newCursorPos, newCursorPos);
        };

        displayInput.addEventListener('input', handleInput);
        displayInput.addEventListener('keydown', handleKeyDown);

        const form = displayInput.closest('form');
        if (form) {
            form.addEventListener('submit', () => {
                hiddenInput.value = displayInput.value.replace(/\D/g, '');
            });
        }
    });
</script>
@endpush
