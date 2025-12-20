@php
    $inputClasses = 'w-full rounded-2xl border border-gray-200 bg-white px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring focus:ring-indigo-100 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 dark:placeholder-slate-500';
    $rawPlusMinusRows = old('grades_plus_minus', $nilaiMutu->grades_plus_minus ?? []);
    $rawAbRows = old('grades_ab', $nilaiMutu->grades_ab ?? []);

    $normalizeRows = static function ($rows) {
        return collect($rows)->mapWithKeys(function ($row) {
            $letter = strtoupper(trim($row['letter'] ?? ''));
            if ($letter === '') {
                return [];
            }

            return [
                $letter => [
                    'letter' => $letter,
                    'min_score' => $row['min_score'] ?? null,
                    'max_score' => $row['max_score'] ?? null,
                    'grade_point' => $row['grade_point'] ?? null,
                ],
            ];
        });
    };

    $plusMinusMap = $normalizeRows($rawPlusMinusRows);
    $abMap = $normalizeRows($rawAbRows);

    $plusMinusTemplateLetters = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'E'];
    $abTemplateLetters = ['A', 'AB', 'B', 'BC', 'C', 'CD', 'D', 'E'];

    $plusMinusRows = collect($plusMinusTemplateLetters)->map(function ($letter) use ($plusMinusMap) {
        $row = $plusMinusMap->get($letter, []);
        return [
            'letter' => $letter,
            'min_score' => $row['min_score'] ?? null,
            'max_score' => $row['max_score'] ?? null,
            'grade_point' => $row['grade_point'] ?? null,
        ];
    })->values()->all();

    $extraPlusRows = $plusMinusMap->except($plusMinusTemplateLetters)->values()->all();
    if ($extraPlusRows) {
        $plusMinusRows = array_merge($plusMinusRows, $extraPlusRows);
    }

    $abRows = collect($abTemplateLetters)->map(function ($letter) use ($abMap) {
        $row = $abMap->get($letter, []);
        return [
            'letter' => $letter,
            'min_score' => $row['min_score'] ?? null,
            'max_score' => $row['max_score'] ?? null,
            'grade_point' => $row['grade_point'] ?? null,
        ];
    })->values()->all();

    $extraAbRows = $abMap->except($abTemplateLetters)->values()->all();
    if ($extraAbRows) {
        $abRows = array_merge($abRows, $extraAbRows);
    }

    $defaultPlusMinusPreset = [
        ['letter' => 'A', 'min_score' => 85, 'max_score' => 100, 'grade_point' => 4.0],
        ['letter' => 'A-', 'min_score' => 80, 'max_score' => 84.99, 'grade_point' => 3.7],
        ['letter' => 'B+', 'min_score' => 75, 'max_score' => 79.99, 'grade_point' => 3.3],
        ['letter' => 'B', 'min_score' => 70, 'max_score' => 74.99, 'grade_point' => 3.0],
        ['letter' => 'B-', 'min_score' => 65, 'max_score' => 69.99, 'grade_point' => 2.7],
        ['letter' => 'C+', 'min_score' => 60, 'max_score' => 64.99, 'grade_point' => 2.3],
        ['letter' => 'C', 'min_score' => 55, 'max_score' => 59.99, 'grade_point' => 2.0],
        ['letter' => 'D', 'min_score' => 45, 'max_score' => 54.99, 'grade_point' => 1.0],
        ['letter' => 'E', 'min_score' => 0, 'max_score' => 44.99, 'grade_point' => 0.0],
    ];

    $defaultAbPreset = [
        ['letter' => 'A', 'min_score' => 85, 'max_score' => 100, 'grade_point' => 4.0],
        ['letter' => 'AB', 'min_score' => 80, 'max_score' => 84.99, 'grade_point' => 3.5],
        ['letter' => 'B', 'min_score' => 75, 'max_score' => 79.99, 'grade_point' => 3.0],
        ['letter' => 'BC', 'min_score' => 70, 'max_score' => 74.99, 'grade_point' => 2.5],
        ['letter' => 'C', 'min_score' => 60, 'max_score' => 69.99, 'grade_point' => 2.0],
        ['letter' => 'CD', 'min_score' => 55, 'max_score' => 59.99, 'grade_point' => 1.5],
        ['letter' => 'D', 'min_score' => 45, 'max_score' => 54.99, 'grade_point' => 1.0],
        ['letter' => 'E', 'min_score' => 0, 'max_score' => 44.99, 'grade_point' => 0.0],
    ];
@endphp

<form action="{{ $action }}" method="POST" class="space-y-8">
    @csrf
    @if(!empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="space-y-1">
            <label for="kampus" class="text-sm font-semibold text-gray-700 dark:text-slate-200">Nama Kampus</label>
            <input type="text" id="kampus" name="kampus" class="{{ $inputClasses }}"
                   placeholder="Contoh: Universitas Negeri Nusantara"
                   value="{{ old('kampus', $nilaiMutu->kampus) }}">
            @error('kampus')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="space-y-1">
            <label for="program_studi" class="text-sm font-semibold text-gray-700 dark:text-slate-200">Program Studi</label>
            <input type="text" id="program_studi" name="program_studi" class="{{ $inputClasses }}"
                   placeholder="Contoh: Teknik Informatika"
                   value="{{ old('program_studi', $nilaiMutu->program_studi) }}">
            @error('program_studi')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="space-y-1">
            <label for="kurikulum" class="text-sm font-semibold text-gray-700 dark:text-slate-200">Kurikulum</label>
            <input type="text" id="kurikulum" name="kurikulum" class="{{ $inputClasses }}"
                   placeholder="Contoh: 2020"
                   value="{{ old('kurikulum', $nilaiMutu->kurikulum) }}">
            @error('kurikulum')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="space-y-1">
            <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Status Profil</label>
            <label class="inline-flex items-center gap-2 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 cursor-pointer dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <input type="checkbox" name="is_active" value="1"
                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                       @checked(old('is_active', $nilaiMutu->is_active))>
                Tandai sebagai profil aktif
            </label>
            @error('is_active')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="space-y-1">
        <label for="notes" class="text-sm font-semibold text-gray-700 dark:text-slate-200">Catatan</label>
        <textarea id="notes" name="notes" rows="3" class="{{ $inputClasses }} resize-none"
                  placeholder="Tambahkan catatan khusus, misal syarat beasiswa atau kebijakan dosen wali.">{{ old('notes', $nilaiMutu->notes) }}</textarea>
        @error('notes')
            <p class="text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    @php
        $defaultGroup = count($plusMinusRows) ? 'grades_plus_minus' : 'grades_ab';
        $activeGroup = old('grade_mode', $defaultGroup);
    @endphp

    <div class="flex flex-wrap gap-2">
        <input type="hidden" name="grade_mode" value="{{ $activeGroup }}" data-grade-mode-input>
        <button type="button"
                class="inline-flex items-center gap-2 rounded-2xl border px-4 py-2 text-sm font-semibold transition {{ $activeGroup === 'grades_plus_minus' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 text-gray-700 hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200' }}"
                data-grade-toggle="grades_plus_minus">
            Skala Plus / Minus
        </button>
        <button type="button"
                class="inline-flex items-center gap-2 rounded-2xl border px-4 py-2 text-sm font-semibold transition {{ $activeGroup === 'grades_ab' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 text-gray-700 hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200' }}"
                data-grade-toggle="grades_ab">
            Skala AB / BC
        </button>
    </div>

    <div class="space-y-4"
         data-grade-group
         data-field="grades_plus_minus"
         data-next-index="{{ count($plusMinusRows) }}"
         data-template-order="{{ implode(',', $plusMinusTemplateLetters) }}"
         data-visible="{{ $activeGroup === 'grades_plus_minus' ? 'true' : 'false' }}"
         @if($activeGroup !== 'grades_plus_minus') hidden @endif>
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">Skala huruf A-E dengan + / -</p>
                <p class="text-xs text-gray-500 dark:text-slate-400">Contoh: A, A-, B+, dst.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-indigo-200 hover:text-indigo-600 transition data-[active=true]:bg-indigo-600 data-[active=true]:border-indigo-600 data-[active=true]:text-white dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200"
                        data-fill-preset
                        data-target="grades_plus_minus"
                        data-values='@json($defaultPlusMinusPreset)'>
                    Gunakan preset umum
                </button>
                <button type="button"
                        class="inline-flex items-center rounded-2xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-indigo-500"
                        data-add-row>
                    + Baris
                </button>
            </div>
        </div>
        <div class="space-y-3" data-grade-rows>
            @foreach($plusMinusRows as $index => $row)
                @include('nilai-mutu.partials.grade-row', [
                    'field' => 'grades_plus_minus',
                    'index' => $index,
                    'row' => $row,
                    'showErrors' => true,
                ])
            @endforeach
        </div>
        <template data-row-template>
            @include('nilai-mutu.partials.grade-row', [
                'field' => '__FIELD__',
                'index' => '__INDEX__',
                'row' => ['letter' => '', 'min_score' => '', 'max_score' => '', 'grade_point' => ''],
                'showErrors' => false,
            ])
        </template>
    </div>

    <div class="space-y-4"
         data-grade-group
         data-field="grades_ab"
         data-next-index="{{ count($abRows) }}"
         data-template-order="{{ implode(',', $abTemplateLetters) }}"
         data-visible="{{ $activeGroup === 'grades_ab' ? 'true' : 'false' }}"
         @if($activeGroup !== 'grades_ab') hidden @endif>
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">Skala huruf A-E versi AB/BC</p>
                <p class="text-xs text-gray-500 dark:text-slate-400">Contoh: A, AB, B, BC, dst.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:border-indigo-200 hover:text-indigo-600 transition data-[active=true]:bg-indigo-600 data-[active=true]:border-indigo-600 data-[active=true]:text-white dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200"
                        data-fill-preset
                        data-target="grades_ab"
                        data-values='@json($defaultAbPreset)'>
                    Gunakan preset AB/BC
                </button>
                <button type="button"
                        class="inline-flex items-center rounded-2xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-indigo-500"
                        data-add-row>
                    + Baris
                </button>
            </div>
        </div>
        <div class="space-y-3" data-grade-rows>
            @foreach($abRows as $index => $row)
                @include('nilai-mutu.partials.grade-row', [
                    'field' => 'grades_ab',
                    'index' => $index,
                    'row' => $row,
                    'showErrors' => true,
                ])
            @endforeach
        </div>
        <template data-row-template>
            @include('nilai-mutu.partials.grade-row', [
                'field' => '__FIELD__',
                'index' => '__INDEX__',
                'row' => ['letter' => '', 'min_score' => '', 'max_score' => '', 'grade_point' => ''],
                'showErrors' => false,
            ])
        </template>
    </div>

    <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-100 dark:border-slate-700">
        <a href="{{ route('nilai-mutu.index') }}"
           class="inline-flex items-center rounded-2xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-600 hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200">
            Batal
        </a>
        <button type="submit"
                class="inline-flex items-center rounded-2xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500">
            {{ $submitLabel ?? 'Simpan Profil' }}
        </button>
    </div>
</form>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modeInput = document.querySelector('[data-grade-mode-input]');
                const toggleButtons = Array.from(document.querySelectorAll('[data-grade-toggle]'));
                const groupElements = Array.from(document.querySelectorAll('[data-grade-group]'));
                const managers = groupElements.map((element) => createGroupManager(element));

                toggleButtons.forEach((button) => {
                    button.addEventListener('click', () => setActiveGroup(button.dataset.gradeToggle));
                });

                setActiveGroup(modeInput?.value || managers[0]?.field || null);

                function setActiveGroup(target) {
                    if (!target || !modeInput) {
                        return;
                    }

                    modeInput.value = target;

                    toggleButtons.forEach((button) => {
                        const isActive = button.dataset.gradeToggle === target;
                        button.classList.toggle('bg-indigo-600', isActive);
                        button.classList.toggle('text-white', isActive);
                        button.classList.toggle('border-indigo-600', isActive);
                        button.classList.toggle('border-gray-200', !isActive);
                        button.classList.toggle('text-gray-700', !isActive);
                        button.classList.toggle('dark:border-slate-600', !isActive);
                        button.classList.toggle('dark:text-slate-200', !isActive);
                        button.classList.toggle('dark:hover:border-indigo-400', !isActive);
                        button.classList.toggle('dark:hover:text-indigo-200', !isActive);
                    });

                    groupElements.forEach((group) => {
                        if (group.dataset.field === target) {
                            group.hidden = false;
                            group.dataset.visible = 'true';
                        } else {
                            group.hidden = true;
                            group.dataset.visible = 'false';
                        }
                    });
                }

                function createGroupManager(root) {
                    const field = root.dataset.field;
                    const container = root.querySelector('[data-grade-rows]');
                    const template = root.querySelector('template[data-row-template]');
                    let nextIndex = Number(root.dataset.nextIndex || (container?.children.length ?? 0));
                    const orderMap = buildOrderMap(root.dataset.templateOrder || '');
                    let presetActive = false;
                    let savedSnapshot = snapshot();

                    bindExistingRows();
                    bindAddButton();
                    bindPresetButton();

                    return { field };

                    function buildOrderMap(raw) {
                        return new Map(
                            raw
                                .split(',')
                                .map((item, index) => [normalize(item), index])
                                .filter(([letter]) => letter)
                        );
                    }

                    function normalize(value) {
                        return (value || '').trim().toUpperCase();
                    }

                    function snapshot() {
                        if (!container) {
                            return [];
                        }

                        return Array.from(container.querySelectorAll('[data-grade-row]')).map((row) => ({
                            letter: row.querySelector('[data-letter-input]')?.value ?? '',
                            min_score: row.querySelector('[data-min-input]')?.value ?? '',
                            max_score: row.querySelector('[data-max-input]')?.value ?? '',
                            grade_point: row.querySelector('[data-point-input]')?.value ?? '',
                        }));
                    }

                    function applyRows(rows) {
                        if (!container) {
                            return;
                        }

                        container.innerHTML = '';
                        nextIndex = 0;

                        const list = rows.length ? rows : [{}];
                        list.forEach((rowData) => addRow(rowData));
                    }

                    function addRow(values = {}) {
                        const row = renderRow(values);
                        if (!row) {
                            return null;
                        }
                        bindRow(row);
                        placeRow(row);
                        return row;
                    }

                    function renderRow(values = {}) {
                        if (!template || !container) {
                            return null;
                        }

                        const index = nextIndex++;
                        const html = template.innerHTML
                            .replace(/__FIELD__/g, field)
                            .replace(/__INDEX__/g, index);
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = html.trim();
                        const row = wrapper.firstElementChild;
                        if (!row) {
                            return null;
                        }

                        container.appendChild(row);
                        const letterInput = row.querySelector('[data-letter-input]');
                        const minInput = row.querySelector('[data-min-input]');
                        const maxInput = row.querySelector('[data-max-input]');
                        const pointInput = row.querySelector('[data-point-input]');

                        if (letterInput) letterInput.value = values.letter ?? '';
                        if (minInput) minInput.value = values.min_score ?? '';
                        if (maxInput) maxInput.value = values.max_score ?? '';
                        if (pointInput) pointInput.value = values.grade_point ?? '';

                        return row;
                    }

                    function placeRow(row) {
                        if (!container) {
                            return;
                        }

                        const letter = normalize(row.querySelector('[data-letter-input]')?.value);
                        if (!letter) {
                            return;
                        }

                        const desiredIndex = orderMap.has(letter) ? orderMap.get(letter) : Number.MAX_SAFE_INTEGER;
                        const siblings = Array.from(container.querySelectorAll('[data-grade-row]')).filter((node) => node !== row);

                        let inserted = false;
                        for (const sibling of siblings) {
                            const siblingLetter = normalize(sibling.querySelector('[data-letter-input]')?.value);
                            const siblingIndex = orderMap.has(siblingLetter) ? orderMap.get(siblingLetter) : Number.MAX_SAFE_INTEGER;
                            if (siblingIndex > desiredIndex) {
                                container.insertBefore(row, sibling);
                                inserted = true;
                                break;
                            }
                        }

                        if (!inserted) {
                            container.appendChild(row);
                        }
                    }

                    function ensureRowExists() {
                        if (!container || container.children.length > 0) {
                            return;
                        }
                        addRow();
                        savedSnapshot = snapshot();
                    }

                    function bindRow(row) {
                        const removeButton = row.querySelector('[data-remove-row]');
                        removeButton?.addEventListener('click', () => {
                            row.remove();
                            presetActive = false;
                            ensureRowExists();
                            savedSnapshot = snapshot();
                        });

                        const inputs = row.querySelectorAll('input');
                        inputs.forEach((input) => {
                            input.addEventListener('input', () => {
                                presetActive = false;
                                if (input.dataset.letterInput !== undefined) {
                                    placeRow(row);
                                }
                                savedSnapshot = snapshot();
                            });
                            if (input.dataset.letterInput !== undefined) {
                                input.addEventListener('blur', () => placeRow(row));
                            }
                        });
                    }

                    function bindExistingRows() {
                        Array.from(container?.querySelectorAll('[data-grade-row]') ?? []).forEach((row) => {
                            bindRow(row);
                            placeRow(row);
                        });
                        savedSnapshot = snapshot();
                    }

                    function bindAddButton() {
                        const addButton = root.querySelector('[data-add-row]');
                        addButton?.addEventListener('click', () => {
                            presetActive = false;
                            addRow();
                            savedSnapshot = snapshot();
                        });
                    }

                    function bindPresetButton() {
                        const presetButton = root.querySelector('[data-fill-preset]');
                        if (!presetButton) {
                            return;
                        }

                        presetButton.addEventListener('click', () => {
                            if (presetActive) {
                                presetActive = false;
                                applyRows(savedSnapshot);
                                presetButton.dataset.active = 'false';
                                return;
                            }

                            savedSnapshot = snapshot();

                            let presetRows = [];
                            try {
                                presetRows = JSON.parse(presetButton.dataset.values || '[]');
                            } catch (error) {
                                presetRows = [];
                            }

                            presetActive = true;
                            applyRows(presetRows);
                            presetButton.dataset.active = 'true';
                        });
                    }
                }
            });
        </script>
    @endpush
@endonce
