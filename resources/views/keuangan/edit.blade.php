@extends('layouts.app')

@section('page_title', 'Edit Transaksi')

@section('content')
    @php
        $nominalRaw = old('nominal', $keuangan->nominal);
        $nominalDisplay = $nominalRaw !== null && $nominalRaw !== '' && is_numeric($nominalRaw)
            ? number_format((float) $nominalRaw, 0, ',', '.')
            : $nominalRaw;
        $nominalValue = is_numeric($nominalRaw)
            ? ((float) $nominalRaw == (int) $nominalRaw ? (int) $nominalRaw : (float) $nominalRaw)
            : $nominalRaw;
    @endphp
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-slate-400">Perbarui transaksi agar catatan keuangan tetap akurat</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">Edit Transaksi</h2>
            </div>
            <a href="{{ route('keuangan.index') }}" class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">
                &larr; Kembali
            </a>
        </div>

        <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
            <form action="{{ route('keuangan.update', $keuangan) }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="jenis" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Jenis Transaksi</label>
                        <select name="jenis"
                                id="jenis"
                                class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required>
                            <option value="pemasukan" {{ old('jenis', $keuangan->jenis) === 'pemasukan' ? 'selected' : '' }}>Pemasukan</option>
                            <option value="pengeluaran" {{ old('jenis', $keuangan->jenis) === 'pengeluaran' ? 'selected' : '' }}>Pengeluaran</option>
                        </select>
                        @error('jenis')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="tanggal" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Tanggal</label>
                        <input type="date"
                               name="tanggal"
                               id="tanggal"
                               value="{{ old('tanggal', \Illuminate\Support\Carbon::parse($keuangan->tanggal)->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                               required>
                        @error('tanggal')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="kategori" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Kategori</label>
                        <input type="text"
                               name="kategori"
                               id="kategori"
                               value="{{ old('kategori', $keuangan->kategori) }}"
                               class="mt-1 block w-full rounded-xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                               placeholder="Contoh: Makan, Gaji, Transport"
                               required>
                        @error('kategori')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nominal_display" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Nominal</label>
                        <div class="relative mt-1 rounded-xl shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Rp</span>
                            </div>
                            <input type="text"
                                   id="nominal_display"
                                   inputmode="numeric"
                                   value="{{ $nominalDisplay }}"
                                   class="block w-full rounded-xl border-gray-200 pl-10 pr-4 focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                   placeholder="0"
                                   required>
                            <input type="hidden"
                                   name="nominal"
                                   id="nominal"
                                   value="{{ $nominalValue }}">
                        </div>
                        @error('nominal')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Deskripsi</label>
                    <textarea name="deskripsi"
                              id="deskripsi"
                              rows="4"
                              class="mt-1 block w-full rounded-2xl border-gray-200 focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                              placeholder="Catatan tambahan (opsional)">{{ old('deskripsi', $keuangan->deskripsi) }}</textarea>
                    @error('deskripsi')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('keuangan.index') }}" class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-white font-semibold shadow hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                            style="background-color: #1261DE;">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const displayInput = document.getElementById('nominal_display');
        const hiddenInput = document.getElementById('nominal');

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
