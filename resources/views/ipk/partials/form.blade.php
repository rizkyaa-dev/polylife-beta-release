@php
    $inputClasses = 'w-full rounded-2xl border border-gray-200 bg-white px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100';
    $enforceSequential = $enforceSequential ?? false;
    $nextSemester = $nextSemester ?? null;
    $ipk = $ipk ?? null;
@endphp

<form id="ipk-form" action="{{ $action }}" method="POST" class="space-y-6"
      @if($enforceSequential && $nextSemester) data-next-semester="{{ $nextSemester }}" @endif>
    @csrf
    @isset($method)
        @if(strtoupper($method) !== 'POST')
            @method($method)
        @endif
    @endisset

    <div class="grid gap-6 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="semester" class="text-sm font-semibold text-gray-700">Semester</label>
            <input type="number" name="semester" id="semester" min="1" max="14"
                   class="{{ $inputClasses }}"
                   value="{{ old('semester', optional($ipk)->semester) }}">
            <p class="text-xs text-gray-500">
                Isi angka semester (1-14)@if($enforceSequential && $nextSemester). Semester berikutnya: {{ $nextSemester }}@endif.
            </p>
            @error('semester')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="space-y-1">
            <label for="academic_year" class="text-sm font-semibold text-gray-700">Tahun akademik</label>
            <input type="text" name="academic_year" id="academic_year" placeholder="2024/2025"
                   class="{{ $inputClasses }}"
                   value="{{ old('academic_year', optional($ipk)->academic_year) }}">
            <p class="text-xs text-gray-500">Format: YYYY/YYYY (opsional).</p>
            @error('academic_year')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="ips_actual" class="text-sm font-semibold text-gray-700">IPS</label>
            <input type="number" name="ips_actual" id="ips_actual" step="0.01" min="0" max="4"
                   class="{{ $inputClasses }}"
                   value="{{ old('ips_actual', optional($ipk)->ips_actual) }}">
            <p class="text-xs text-gray-500">Masukkan IPS semester ini (0 - 4.00).</p>
            @error('ips_actual')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="space-y-1">
            <label for="remarks" class="text-sm font-semibold text-gray-700">Catatan (opsional)</label>
            <textarea id="remarks" name="remarks" rows="3" class="{{ $inputClasses }} resize-none"
                      placeholder="Contoh: fokus perbaiki nilai praktikum.">{{ old('remarks', optional($ipk)->remarks) }}</textarea>
            @error('remarks')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex flex-wrap gap-3 items-center justify-between pt-4 border-t border-gray-100 dark:border-slate-800">
        <a href="{{ route('ipk.index') }}"
           class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
            Batal
        </a>
        <button type="submit"
                class="inline-flex items-center rounded-2xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500">
            {{ $submitLabel ?? 'Simpan' }}
        </button>
    </div>
</form>

@if($enforceSequential && $nextSemester)
    <script>
        (function () {
            const form = document.getElementById('ipk-form');
            if (!form) {
                return;
            }

            const semesterInput = form.querySelector('#semester');
            const submitButton = form.querySelector('button[type="submit"]');
            const expected = Number(form.dataset.nextSemester);

            if (!semesterInput || !submitButton || !Number.isFinite(expected)) {
                return;
            }

            const updateState = () => {
                const value = Number(semesterInput.value);
                const isValid = Number.isInteger(value) && value === expected;
                submitButton.disabled = !isValid;
                submitButton.classList.toggle('opacity-50', !isValid);
                submitButton.classList.toggle('cursor-not-allowed', !isValid);
            };

            updateState();
            semesterInput.addEventListener('input', updateState);
        })();
    </script>
@endif
