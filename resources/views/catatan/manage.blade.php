@extends('layouts.app')

@section('page_title', 'Manage Catatan')

@section('content')
    @php
        $filters = $filters ?? [];
        $search = (string) ($filters['q'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'latest');
        $resultCount = $catatans->total();
    @endphp

    <div class="space-y-6" data-catatan-manage>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm text-indigo-600 font-semibold uppercase tracking-wide dark:text-indigo-300">Mode Kelola</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Manage Catatan</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Cari, filter, lalu pilih massal catatan yang ingin kamu rapikan.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('catatan.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:border-gray-300 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600">
                    &larr; Kembali ke daftar
                </a>
                <a href="{{ route('catatan.sampah') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:border-gray-300 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600">
                    Sampah ({{ $trashCount }})
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <form method="GET" action="{{ route('catatan.manage') }}" class="grid gap-4 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                <div class="space-y-2">
                    <label for="q" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Cari Catatan</label>
                    <input id="q"
                           type="text"
                           name="q"
                           value="{{ $search }}"
                           placeholder="Judul atau isi catatan"
                           class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                </div>
                <div class="space-y-2">
                    <label for="date_from" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Dari Tanggal</label>
                    <input id="date_from"
                           type="date"
                           name="date_from"
                           value="{{ $dateFrom }}"
                           class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                </div>
                <div class="space-y-2">
                    <label for="date_to" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Sampai</label>
                    <input id="date_to"
                           type="date"
                           name="date_to"
                           value="{{ $dateTo }}"
                           class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                </div>
                <div class="space-y-2">
                    <label for="sort" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Urutkan</label>
                    <select id="sort"
                            name="sort"
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-950/40 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-500/20">
                        <option value="latest" @selected($sort === 'latest')>Terbaru</option>
                        <option value="oldest" @selected($sort === 'oldest')>Terlama</option>
                        <option value="title_asc" @selected($sort === 'title_asc')>Judul A-Z</option>
                        <option value="title_desc" @selected($sort === 'title_desc')>Judul Z-A</option>
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit"
                            class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Terapkan
                    </button>
                    <a href="{{ route('catatan.manage') }}"
                       class="inline-flex items-center rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600 hover:border-gray-300 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-600">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Hasil Filter</p>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $resultCount }} catatan ditemukan</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Halaman {{ $catatans->currentPage() }} dari {{ $catatans->lastPage() }}</p>
                </div>
                <label class="inline-flex items-center gap-3 rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-slate-700 dark:text-slate-200">
                    <input type="checkbox"
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:border-indigo-400 dark:checked:bg-indigo-500 dark:focus:ring-offset-slate-900"
                           data-select-all>
                    Pilih semua di halaman ini
                </label>
            </div>

            <div class="mt-4">
                @if ($catatans->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/60 p-8 text-center dark:border-slate-700 dark:bg-slate-900/40">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">Tidak ada catatan yang cocok.</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-slate-400">Coba ubah kata kunci atau filter tanggal.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('catatan.bulk-trash') }}" class="space-y-4" data-bulk-form>
                        @csrf
                        <input type="hidden" name="q" value="{{ $search }}">
                        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                        <input type="hidden" name="date_to" value="{{ $dateTo }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="page" value="{{ request('page') }}">

                        <div class="flex flex-col gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4 md:flex-row md:items-center md:justify-between dark:border-indigo-500/30 dark:bg-indigo-500/10">
                            <div>
                                <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">Aksi Massal</p>
                                <p class="text-xs text-indigo-700 dark:text-indigo-200">
                                    <span data-selected-count>0</span> catatan dipilih
                                </p>
                            </div>
                            <button type="submit"
                                    disabled
                                    data-bulk-submit
                                    onclick="return confirm('Pindahkan semua catatan terpilih ke sampah?');"
                                    class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:bg-rose-300 hover:bg-rose-500">
                                Pindahkan ke Sampah
                            </button>
                        </div>

                        <div class="space-y-3">
                            @foreach ($catatans as $catatan)
                                <label class="flex gap-4 rounded-2xl border border-gray-100 bg-gray-50/60 p-4 shadow-sm transition hover:border-indigo-200 hover:bg-white dark:border-slate-800 dark:bg-slate-900/50 dark:hover:border-indigo-500/30 dark:hover:bg-slate-900/80">
                                    <input type="checkbox"
                                           name="catatan_ids[]"
                                           value="{{ $catatan->id }}"
                                           class="mt-1 h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:border-indigo-400 dark:checked:bg-indigo-500 dark:focus:ring-offset-slate-900"
                                           data-catatan-checkbox>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <h4 class="text-base font-semibold text-gray-900 dark:text-white">{{ $catatan->judul }}</h4>
                                                <p class="mt-1 text-sm text-gray-600 line-clamp-3 dark:text-slate-300">
                                                    {{ $catatan->previewForDisplay() !== '' ? $catatan->previewForDisplay() : 'Preview disembunyikan' }}
                                                </p>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-600 border border-indigo-100 dark:border-indigo-500/30 dark:bg-slate-950/50 dark:text-indigo-200">
                                                    {{ \Illuminate\Support\Carbon::parse($catatan->tanggal)->translatedFormat('d M Y') }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-slate-400">
                                            <span>Dibuat {{ $catatan->created_at?->diffForHumans() ?? '-' }}</span>
                                            <a href="{{ route('catatan.edit', $catatan) }}"
                                               class="font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200">
                                                Edit catatan
                                            </a>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </form>
                    <div class="mt-6">
                        {{ $catatans->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const container = document.querySelector('[data-catatan-manage]');
            if (!container) return;

            const selectAll = container.querySelector('[data-select-all]');
            const checkboxes = Array.from(container.querySelectorAll('[data-catatan-checkbox]'));
            const selectedCount = container.querySelector('[data-selected-count]');
            const submitButton = container.querySelector('[data-bulk-submit]');

            if (!selectAll || !checkboxes.length || !selectedCount || !submitButton) {
                return;
            }

            const syncState = () => {
                const checked = checkboxes.filter((checkbox) => checkbox.checked).length;
                selectedCount.textContent = String(checked);
                submitButton.disabled = checked === 0;
                selectAll.checked = checked > 0 && checked === checkboxes.length;
                selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
            };

            selectAll.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                syncState();
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', syncState);
            });

            syncState();
        })();
    </script>
@endpush
