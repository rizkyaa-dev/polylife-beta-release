@extends('layouts.app')

@section('page_title', ucfirst($jenis) . ' Keuangan')

@section('content')
@php
    $nominalRaw = old('nominal');
    $nominalDisplay = $nominalRaw !== null && $nominalRaw !== '' && is_numeric($nominalRaw)
        ? number_format((float) $nominalRaw, 0, ',', '.')
        : $nominalRaw;
    $nominalValue = is_numeric($nominalRaw)
        ? ((float) $nominalRaw == (int) $nominalRaw ? (int) $nominalRaw : (float) $nominalRaw)
        : $nominalRaw;
@endphp
<div class="max-w-3xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-3xl border dark:bg-slate-900 dark:border-slate-800">
    <div class="p-6 sm:p-8 text-gray-900 dark:text-slate-100">
        <form action="{{ route('keuangan.store') }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="jenis" value="{{ $jenis }}">

            <div>
                <label for="kategori" class="form-label">Kategori</label>
                <input type="text" name="kategori" id="kategori" value="{{ old('kategori') }}" class="mt-1 form-input" required>
                @error('kategori')
                    <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="deskripsi" class="form-label">Deskripsi</label>
                <textarea name="deskripsi" id="deskripsi" rows="3" class="mt-1 form-input" placeholder="Tambahkan catatan singkat">{{ old('deskripsi') }}</textarea>
                @error('deskripsi')
                    <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="nominal_display" class="form-label">Nominal</label>
                <div class="relative mt-1 rounded-xl shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Rp</span>
                    </div>
                    <input type="text"
                           id="nominal_display"
                           inputmode="numeric"
                           value="{{ $nominalDisplay }}"
                           class="form-input pl-10"
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

            <div>
                <label for="tanggal" class="form-label">Tanggal</label>
                <input type="date" name="tanggal" id="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" class="mt-1 form-input" required>
                @error('tanggal')
                    <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end pt-2">
                <button type="submit" class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Simpan
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
