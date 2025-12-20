@props([
    'action',
    'method' => 'POST',
    'jadwal' => null,
    'submitLabel' => 'Simpan',
    'matkuls' => collect(),
    'deleteUrl' => null,
])

@php
    $defaultMulai = old('tanggal_mulai', request('tanggal_mulai', optional(optional($jadwal)->tanggal_mulai ?? now())->format('Y-m-d')));
    $defaultSelesai = old('tanggal_selesai', request('tanggal_selesai', optional(optional($jadwal)->tanggal_selesai ?? now())->format('Y-m-d')));
    $jenisOptions = [
        'kuliah' => 'Kuliah',
        'libur' => 'Libur',
        'uts' => 'UTS',
        'uas' => 'UAS',
        'lomba' => 'Lomba',
        'lainnya' => 'Lainnya',
    ];
    $matkuls = $matkuls ?? collect();
    $selectedMatkulIds = collect(old('matkul_ids', optional($jadwal)->matkulIds()?->toArray() ?? []))
        ->map(fn ($id) => (string) $id)
        ->all();
    $semesterFilters = $matkuls->pluck('semester')->filter()->unique()->sort()->values();
    $showMatkulForm = (bool) old('matkul_create', false);
    $labelClass = 'form-label';
    $helperClass = 'form-helper';
    $inputClass = 'form-input';
@endphp

<form action="{{ $action }}" method="POST" class="space-y-6" data-matkul-section>
    @csrf
    @if(!in_array(strtoupper($method), ['GET', 'POST']))
        @method($method)
    @endif

    <div>
        <label for="jenis" class="{{ $labelClass }}">Jenis Agenda</label>
        <p class="{{ $helperClass }}">Batasi pada aktivitas kampus seperti kuliah, libur akademik, UTS, UAS, lomba, dst.</p>
        <select id="jenis"
                name="jenis"
                class="mt-2 {{ $inputClass }}"
                required>
            @foreach($jenisOptions as $value => $label)
                <option value="{{ $value }}" {{ old('jenis', $jadwal->jenis ?? 'kuliah') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('jenis')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="tanggal_mulai" class="{{ $labelClass }}">Tanggal Mulai</label>
            <input type="date"
                   id="tanggal_mulai"
                   name="tanggal_mulai"
                   value="{{ $defaultMulai }}"
                   class="mt-2 {{ $inputClass }}"
                   required>
            @error('tanggal_mulai')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="tanggal_selesai" class="{{ $labelClass }}">Tanggal Selesai</label>
            <input type="date"
                   id="tanggal_selesai"
                   name="tanggal_selesai"
                   value="{{ $defaultSelesai }}"
                   class="mt-2 {{ $inputClass }}"
                   required>
            @error('tanggal_selesai')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="semester" class="{{ $labelClass }}">Semester</label>
            <input type="number"
                   id="semester"
                   name="semester"
                   value="{{ old('semester', $jadwal->semester ?? '') }}"
                   class="mt-2 {{ $inputClass }}"
                   min="1"
                   max="14"
                   placeholder="Misal: 3">
            @error('semester')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="catatan_tambahan" class="{{ $labelClass }}">Catatan Tambahan</label>
            <textarea id="catatan_tambahan"
                      name="catatan_tambahan"
                      rows="4"
                      class="mt-2 {{ $inputClass }}"
                      placeholder="Tuliskan detail penting seperti topik kuliah, pengingat lomba, dll.">{{ old('catatan_tambahan', $jadwal->catatan_tambahan ?? '') }}</textarea>
            @error('catatan_tambahan')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="space-y-4 rounded-3xl border border-dashed border-indigo-200 p-5 dark:border-indigo-500/40 dark:bg-slate-900/40" data-matkul-card>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">Tautkan Mata Kuliah</p>
                <p class="{{ $helperClass }}">Pilih matkul yang relevan, PolyLife akan menyimpan daftar ID dalam format <code>id;id;id;</code>.</p>
            </div>
            <button type="button"
                    class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-300"
                    data-toggle-matkul-form>
                {{ $showMatkulForm ? 'Batalkan matkul baru' : '+ Tambah Matkul Baru' }}
            </button>
        </div>

        @if($matkuls->isEmpty())
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-gray-700 dark:border-indigo-500/40 dark:bg-indigo-900/30 dark:text-indigo-100">
                Belum ada matkul tersimpan. Gunakan tombol <strong>Tambah Matkul Baru</strong> untuk membuat satu secara cepat atau kunjungi menu Matkul.
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase dark:text-slate-300">Filter Semester</label>
                    <select class="mt-2 form-input text-sm"
                            data-matkul-filter>
                        <option value="">Semua semester</option>
                        @foreach($semesterFilters as $semester)
                            <option value="{{ $semester }}">
                                Semester {{ $semester }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase dark:text-slate-300">Matkul terpilih</label>
                    <button type="button"
                            class="mt-3 inline-flex items-center gap-2 rounded-full border border-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 transition hover:border-indigo-300 hover:text-indigo-900 dark:border-indigo-500/40 dark:text-indigo-200 dark:hover:border-indigo-300 dark:hover:text-indigo-100"
                            data-matkul-count
                            data-select-state="idle">
                        {{ count($selectedMatkulIds) }} dipilih
                    </button>
                </div>
            </div>

            <div class="space-y-3 max-h-[360px] overflow-y-auto pr-1" data-matkul-list>
                @foreach($matkuls as $matkul)
                    @php
                        $isSelected = in_array((string) $matkul->id, $selectedMatkulIds, true);
                    @endphp
                    <label class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white/60 p-3 text-left text-sm transition hover:border-indigo-200 hover:bg-indigo-50/70 dark:border-slate-700 dark:bg-slate-900/60 dark:hover:border-indigo-500/50 dark:hover:bg-slate-800/60"
                           data-matkul-option
                           data-semester="{{ $matkul->semester ?? '' }}">
                        <input type="checkbox"
                               name="matkul_ids[]"
                               value="{{ $matkul->id }}"
                               class="peer sr-only"
                               {{ $isSelected ? 'checked' : '' }}>
                        <span class="flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 bg-white text-transparent transition peer-checked:border-indigo-500 peer-checked:bg-indigo-500 peer-checked:text-white dark:border-slate-600 dark:bg-slate-900">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.145 7.145a1 1 0 01-1.414 0L3.296 9.007a1 1 0 011.414-1.414l3.122 3.122 6.437-6.437a1 1 0 011.435.012z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-800 dark:text-slate-100">
                                {{ $matkul->nama }}
                                @if($matkul->kode)
                                    <span class="text-xs font-normal text-gray-500 dark:text-slate-400">({{ $matkul->kode }})</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                Semester {{ $matkul->semester ?? '—' }}
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('matkul_ids')
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @enderror
        @endif

        <input type="hidden" name="matkul_create" value="{{ $showMatkulForm ? 1 : 0 }}" data-matkul-create-flag>

        <div class="space-y-4 rounded-2xl bg-indigo-50/60 p-4 {{ $showMatkulForm ? '' : 'hidden' }} dark:bg-indigo-900/20" data-matkul-form>
            <p class="text-sm font-semibold text-indigo-700 dark:text-indigo-200">Tambah Matkul Baru</p>
            <div>
                <label class="block text-xs font-semibold text-indigo-700 dark:text-indigo-200">Nama Matkul</label>
                <input type="text"
                       name="matkul_nama"
                       value="{{ old('matkul_nama') }}"
                       class="mt-1 form-input border-indigo-200 focus:border-indigo-400 focus:ring focus:ring-indigo-200/50 dark:border-indigo-500/40 dark:focus:border-indigo-400"
                       placeholder="Contoh: Kalkulus Lanjut">
                @error('matkul_nama')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-indigo-700 dark:text-indigo-200">Kode</label>
                    <input type="text"
                           name="matkul_kode"
                           value="{{ old('matkul_kode') }}"
                           class="mt-1 form-input border-indigo-200 focus:border-indigo-400 focus:ring focus:ring-indigo-200/50 dark:border-indigo-500/40 dark:focus:border-indigo-400"
                           placeholder="Misal: IFK201">
                    @error('matkul_kode')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-indigo-700 dark:text-indigo-200">Semester</label>
                    <input type="number"
                           name="matkul_semester"
                           value="{{ old('matkul_semester') }}"
                           min="1"
                           max="14"
                           class="mt-1 form-input border-indigo-200 focus:border-indigo-400 focus:ring focus:ring-indigo-200/50 dark:border-indigo-500/40 dark:focus:border-indigo-400"
                           placeholder="1-14">
                    @error('matkul_semester')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <p class="text-[11px] text-gray-500">
                Matkul baru otomatis disimpan ke daftar utama dan ditautkan ke jadwal ini.
            </p>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('jadwal.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Kembali ke kalender</a>
        <div class="flex flex-wrap items-center gap-2">
            @if($deleteUrl)
                <a href="{{ $deleteUrl }}"
                   class="inline-flex items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100">
                    Hapus Jadwal
                </a>
            @endif
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1">
                {{ $submitLabel }}
            </button>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const section = document.querySelector('[data-matkul-section]');
            if (!section) return;

            const toggleBtn = section.querySelector('[data-toggle-matkul-form]');
            const matkulForm = section.querySelector('[data-matkul-form]');
            const createFlag = section.querySelector('[data-matkul-create-flag]');
            const filterSelect = section.querySelector('[data-matkul-filter]');
            const countBadge = section.querySelector('[data-matkul-count]');
            const optionNodes = Array.from(section.querySelectorAll('[data-matkul-option]'));
            const checkboxes = optionNodes
                .map((node) => node.querySelector('input[type="checkbox"]'))
                .filter(Boolean);
            let isBadgeHovering = false;

            const getVisibleOptions = () => optionNodes.filter((node) => !node.classList.contains('hidden'));

            const updateCount = () => {
                if (!countBadge) return;
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                const label = `${selected} dipilih`;
                countBadge.dataset.countLabel = label;
                if (!isBadgeHovering) {
                    countBadge.textContent = label;
                }
            };

            const applyFilter = () => {
                if (!filterSelect) return;
                const semester = filterSelect.value || '';
                optionNodes.forEach((node) => {
                    const optionSemester = node.dataset.semester || '';
                    const show = !semester || optionSemester === semester;
                    node.classList.toggle('hidden', !show);
                });
                if (countBadge) {
                    countBadge.dataset.selectState = 'idle';
                }
                updateCount();
            };

            toggleBtn?.addEventListener('click', () => {
                if (!matkulForm || !createFlag || !toggleBtn) return;
                const willShow = matkulForm.classList.contains('hidden');
                matkulForm.classList.toggle('hidden', !willShow);
                createFlag.value = willShow ? '1' : '0';
                toggleBtn.textContent = willShow ? 'Batalkan matkul baru' : '+ Tambah Matkul Baru';
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    if (countBadge) {
                        countBadge.dataset.selectState = 'idle';
                    }
                    updateCount();
                });
            });

            filterSelect?.addEventListener('change', applyFilter);

            countBadge?.addEventListener('mouseenter', () => {
                isBadgeHovering = true;
                const state = countBadge.dataset.selectState === 'all' ? 'Batalkan' : 'Pilih semua';
                countBadge.textContent = state;
            });

            countBadge?.addEventListener('mouseleave', () => {
                isBadgeHovering = false;
                countBadge.textContent = countBadge.dataset.countLabel ?? '0 dipilih';
            });

            countBadge?.addEventListener('click', () => {
                const visibleOptions = getVisibleOptions();
                if (visibleOptions.length === 0) return;
                const selectAll = countBadge.dataset.selectState !== 'all';
                visibleOptions.forEach((node) => {
                    const checkbox = node.querySelector('input[type="checkbox"]');
                    if (!checkbox) return;
                    checkbox.checked = selectAll;
                });
                countBadge.dataset.selectState = selectAll ? 'all' : 'idle';
                countBadge.textContent = countBadge.dataset.countLabel ?? '0 dipilih';
                isBadgeHovering = false;
                updateCount();
            });

            applyFilter();
            updateCount();
        });
    </script>
@endpush
