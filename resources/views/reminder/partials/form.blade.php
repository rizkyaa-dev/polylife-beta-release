@props([
    'action',
    'method' => 'POST',
    'reminder' => null,
    'submitLabel' => 'Simpan Reminder',
    'todolists' => collect(),
    'tugasList' => collect(),
    'jadwals' => collect(),
    'kegiatans' => collect(),
    'selectedTarget' => 'todolist',
])

@php
    $targetOptions = [
        'todolist' => [
            'label' => 'To-Do',
            'helper' => 'Kirim pengingat dari tugas dalam daftar kegiatan sehari-hari.',
            'options' => $todolists,
            'label_key' => 'nama_item',
        ],
        'tugas' => [
            'label' => 'Tugas Kuliah',
            'helper' => 'Terkoneksi langsung dengan daftar tugas perkuliahan.',
            'options' => $tugasList,
            'label_key' => 'nama_tugas',
        ],
        'jadwal' => [
            'label' => 'Agenda Kuliah',
            'helper' => 'Ingatkan agenda penting dari kalender jadwal.',
            'options' => $jadwals,
            'label_key' => 'catatan_tambahan',
        ],
        'kegiatan' => [
            'label' => 'Kegiatan Detail',
            'helper' => 'Pengingat untuk kegiatan turunan dari sebuah jadwal.',
            'options' => $kegiatans,
            'label_key' => 'nama_kegiatan',
        ],
    ];

    $selectedTarget = old('reminder_target', $selectedTarget);
    $defaultDateTime = optional(old('waktu_reminder', $reminder->waktu_reminder ?? now()))->format('Y-m-d\TH:i');
    $defaultActive = old('aktif', $reminder->aktif ?? true);
@endphp

@php
    $labelClass = 'form-label';
    $inputClass = 'form-input';
    $helperClass = 'form-helper';
@endphp

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if(!in_array(strtoupper($method), ['GET', 'POST']))
        @method($method)
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-1 space-y-3">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold dark:text-slate-400">Pilih jenis reminder</p>
            <div class="space-y-2">
                @foreach($targetOptions as $key => $meta)
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border px-3 py-2 transition
                        {{ $selectedTarget === $key ? 'border-indigo-500 bg-indigo-50/70 dark:border-indigo-400/60 dark:bg-indigo-900/30' : 'border-gray-200 bg-white dark:border-slate-700 dark:bg-slate-900/60' }}">
                        <input type="radio"
                               name="reminder_target"
                               value="{{ $key }}"
                               class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700"
                               {{ $selectedTarget === $key ? 'checked' : '' }}>
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-slate-100">{{ $meta['label'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $meta['helper'] }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="lg:col-span-2 space-y-5">
            @foreach($targetOptions as $key => $meta)
                @php
                    $options = $meta['options'];
                    $fieldName = $key . '_id';
                    $selectedId = old($fieldName, $reminder?->{$fieldName});
                @endphp
                <div data-target-panel="{{ $key }}" class="{{ $selectedTarget === $key ? '' : 'hidden' }} space-y-2">
                    <label class="text-sm font-semibold text-gray-800 dark:text-slate-100">{{ $meta['label'] }} terkait</label>
                    @if($options->isEmpty())
                        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-4 text-sm text-gray-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300">
                            Belum ada data {{ strtolower($meta['label']) }}. Tambahkan terlebih dahulu pada menu terkait.
                        </div>
                    @else
                        <select name="{{ $fieldName }}"
                                class="form-input text-sm"
                                {{ $selectedTarget === $key ? 'required' : '' }}>
                            <option value="">Pilih {{ strtolower($meta['label']) }}</option>
                            @foreach($options as $option)
                                @php
                                    $label = $meta['label_key'] === 'catatan_tambahan'
                                        ? ($option->catatan_tambahan ?: ucfirst($option->jenis ?? 'Agenda'))
                                        : ($option->{$meta['label_key']} ?? 'Tanpa nama');
                                    if ($key === 'jadwal' && $option->tanggal_mulai) {
                                        $label .= ' • ' . \Illuminate\Support\Carbon::parse($option->tanggal_mulai)->translatedFormat('d M');
                                    }
                                    if ($key === 'tugas' && $option->deadline) {
                                        $label .= ' • ' . \Illuminate\Support\Carbon::parse($option->deadline)->translatedFormat('d M');
                                    }
                                    if ($key === 'kegiatan' && $option->tanggal_deadline) {
                                        $label .= ' • ' . \Illuminate\Support\Carbon::parse($option->tanggal_deadline)->translatedFormat('d M H:i');
                                    }
                                @endphp
                                <option value="{{ $option->id }}" @selected($selectedId == $option->id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="waktu_reminder" class="{{ $labelClass }}">Waktu Reminder</label>
            <input type="datetime-local"
                   id="waktu_reminder"
                   name="waktu_reminder"
                   value="{{ $defaultDateTime }}"
                   class="mt-2 {{ $inputClass }} focus:border-indigo-400 focus:ring focus:ring-indigo-200/50"
                   required>
            @error('waktu_reminder')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex items-center gap-3">
            <label class="text-sm font-semibold text-gray-800 dark:text-slate-100">Status Aktif</label>
            <label class="inline-flex cursor-pointer items-center reminder-toggle">
                <input type="checkbox"
                       name="aktif"
                       value="1"
                       class="sr-only reminder-toggle-input"
                       {{ $defaultActive ? 'checked' : '' }}>
                <span class="reminder-toggle-track" aria-hidden="true">
                    <span class="reminder-toggle-thumb"></span>
                </span>
            </label>
        </div>
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('reminder.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">← Kembali ke daftar reminder</a>
        <button type="submit"
                class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 dark:bg-indigo-500 dark:hover:bg-indigo-400">
            {{ $submitLabel }}
        </button>
    </div>
</form>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const targetRadios = document.querySelectorAll('input[name="reminder_target"]');
                const panels = document.querySelectorAll('[data-target-panel]');

                const updatePanels = () => {
                    const selected = document.querySelector('input[name="reminder_target"]:checked')?.value;
                    panels.forEach(panel => {
                        const isActive = panel.getAttribute('data-target-panel') === selected;
                        panel.classList.toggle('hidden', !isActive);
                        const select = panel.querySelector('select');
                        if (select) {
                            select.toggleAttribute('required', isActive);
                        }
                    });
                };

                targetRadios.forEach(radio => {
                    radio.addEventListener('change', updatePanels);
                });

                updatePanels();
            });
        </script>
    @endpush

    @push('styles')
        <style>
            .reminder-toggle-track {
                position: relative;
                width: 44px;
                height: 24px;
                border-radius: 9999px;
                background: #e5e7eb;
                box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
                transition: background 220ms ease, box-shadow 220ms ease, transform 200ms ease;
                display: inline-flex;
                align-items: center;
                padding: 0 2px;
                overflow: hidden;
            }

            .reminder-toggle-track::before {
                content: '';
                position: absolute;
                inset: 0;
                border-radius: inherit;
                background: radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.35), transparent 55%);
                opacity: 0;
                transform: scale(0.6);
                transition: opacity 220ms ease, transform 260ms ease;
            }

            .reminder-toggle-thumb {
                position: relative;
                width: 18px;
                height: 18px;
                border-radius: 9999px;
                background: #fff;
                box-shadow: 0 4px 10px rgba(15, 23, 42, 0.18);
                transform: translateX(0);
                transition: transform 240ms ease, box-shadow 240ms ease;
            }

            .reminder-toggle-input:focus-visible + .reminder-toggle-track {
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
            }

            .reminder-toggle-input:active + .reminder-toggle-track .reminder-toggle-thumb {
                transform: translateX(0) scale(0.94);
            }

            .reminder-toggle-input:checked + .reminder-toggle-track {
                background: linear-gradient(120deg, #6366f1, #8b5cf6);
                box-shadow: 0 4px 14px rgba(99, 102, 241, 0.25);
            }

            .reminder-toggle-input:checked + .reminder-toggle-track::before {
                opacity: 1;
                transform: scale(1);
            }

            .reminder-toggle-input:checked + .reminder-toggle-track .reminder-toggle-thumb {
                transform: translateX(20px);
                box-shadow: 0 6px 14px rgba(99, 102, 241, 0.35);
                animation: toggleThumbOn 260ms ease-out;
            }

            .reminder-toggle-input:not(:checked) + .reminder-toggle-track .reminder-toggle-thumb {
                animation: toggleThumbOff 220ms ease-out;
            }

            @keyframes toggleThumbOn {
                0% { transform: translateX(0); }
                60% { transform: translateX(20px) scale(0.9); }
                100% { transform: translateX(20px) scale(1); }
            }

            @keyframes toggleThumbOff {
                0% { transform: translateX(20px); }
                60% { transform: translateX(0) scale(0.9); }
                100% { transform: translateX(0) scale(1); }
            }

            @media (prefers-color-scheme: dark) {
                .reminder-toggle-track {
                    background: #1f2937;
                    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.3);
                }
                .reminder-toggle-thumb {
                    background: #e5e7eb;
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.35);
                }
            }
        </style>
    @endpush
@endonce
