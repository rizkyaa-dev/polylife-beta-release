@php
    $fieldName = $field ?? 'grades_plus_minus';
    $rowIndex = $index ?? 0;
    $rowData = $row ?? ['letter' => '', 'min_score' => '', 'max_score' => '', 'grade_point' => ''];
    $showErrors = $showErrors ?? true;
    $errorBase = "{$fieldName}.{$rowIndex}";
@endphp

<div class="grid gap-3 md:grid-cols-5 items-end rounded-2xl border border-gray-200 p-4 dark:border-slate-700 dark:bg-slate-900/40" data-grade-row>
    <div class="space-y-1">
        <label class="text-xs font-semibold text-gray-600 dark:text-slate-400">Huruf</label>
        <input type="text"
               name="{{ $fieldName }}[{{ $rowIndex }}][letter]"
               maxlength="3"
               class="w-full rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm uppercase focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
               value="{{ old($fieldName.'.'.$rowIndex.'.letter', $rowData['letter'] ?? '') }}"
               data-letter-input>
        @if($showErrors)
            @error($errorBase.'.letter')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        @endif
    </div>
    <div class="space-y-1">
        <label class="text-xs font-semibold text-gray-600 dark:text-slate-400">Nilai Minimum</label>
        <input type="number"
               step="0.01"
               name="{{ $fieldName }}[{{ $rowIndex }}][min_score]"
               class="w-full rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
               value="{{ old($fieldName.'.'.$rowIndex.'.min_score', $rowData['min_score'] ?? '') }}"
               data-min-input>
        @if($showErrors)
            @error($errorBase.'.min_score')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        @endif
    </div>
    <div class="space-y-1">
        <label class="text-xs font-semibold text-gray-600 dark:text-slate-400">Nilai Maksimum</label>
        <input type="number"
               step="0.01"
               name="{{ $fieldName }}[{{ $rowIndex }}][max_score]"
               class="w-full rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
               value="{{ old($fieldName.'.'.$rowIndex.'.max_score', $rowData['max_score'] ?? '') }}"
               data-max-input>
        @if($showErrors)
            @error($errorBase.'.max_score')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        @endif
    </div>
    <div class="space-y-1">
        <label class="text-xs font-semibold text-gray-600 dark:text-slate-400">Bobot (IP)</label>
        <input type="number"
               step="0.01"
               name="{{ $fieldName }}[{{ $rowIndex }}][grade_point]"
               class="w-full rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
               value="{{ old($fieldName.'.'.$rowIndex.'.grade_point', $rowData['grade_point'] ?? '') }}"
               data-point-input>
        @if($showErrors)
            @error($errorBase.'.grade_point')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        @endif
    </div>
    <div class="flex items-center justify-end">
        <button type="button"
                class="text-sm font-semibold text-rose-600 hover:text-rose-500 dark:text-rose-300 dark:hover:text-rose-200"
                data-remove-row>
            Hapus
        </button>
    </div>
</div>
