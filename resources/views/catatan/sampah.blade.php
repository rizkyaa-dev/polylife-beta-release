@extends('layouts.app')

@section('page_title', 'Sampah Catatan')

@section('content')
    <div class="space-y-6">
        @php
            $resultCount = $catatans->total();
        @endphp
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-slate-400">Catatan yang dipindahkan ke sampah akan tersimpan sementara</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">Sampah Catatan</h2>
            </div>
            <a href="{{ route('catatan.index') }}"
               class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200">
                &larr; Kembali ke daftar catatan
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-emerald-100">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-500/10 dark:text-rose-100">
                {{ session('error') }}
            </div>
        @endif

        @if ($catatans->count() === 0)
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                Sampah kosong. Catatan yang kamu hapus sementara akan muncul di sini.
            </div>
        @else
            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" data-catatan-trash-manage>
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Kelola Sampah</p>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $resultCount }} catatan di sampah</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Halaman {{ $catatans->currentPage() }} dari {{ $catatans->lastPage() }}</p>
                    </div>
                    <label class="inline-flex items-center gap-3 rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-100">
                        <input type="checkbox"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-950 dark:text-indigo-300 dark:focus:ring-indigo-300"
                               data-select-all>
                        Pilih semua di halaman ini
                    </label>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="flex flex-col gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4 md:flex-row md:items-center md:justify-between dark:border-indigo-500/20 dark:bg-indigo-500/10">
                        <div>
                            <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">Aksi Massal</p>
                            <p class="text-xs text-indigo-700 dark:text-indigo-200/80">
                                <span data-selected-count>0</span> catatan dipilih
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button"
                                    disabled
                                    data-bulk-restore
                                    class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:bg-emerald-300 hover:bg-emerald-500 dark:disabled:bg-emerald-900/40 dark:disabled:text-emerald-200/60">
                                Pulihkan Terpilih
                            </button>
                            <button type="button"
                                    disabled
                                    data-bulk-delete
                                    class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:bg-rose-300 hover:bg-rose-500 dark:disabled:bg-rose-900/40 dark:disabled:text-rose-200/60">
                                Hapus Permanen
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @foreach ($catatans as $catatan)
                            <label class="flex gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-indigo-200 dark:border-slate-800 dark:bg-slate-900/40 dark:hover:border-indigo-500/40">
                                <input type="checkbox"
                                       name="catatan_ids[]"
                                       value="{{ $catatan->id }}"
                                       class="mt-1 h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-950 dark:text-indigo-300 dark:focus:ring-indigo-300"
                                       data-catatan-checkbox>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-400">Judul</p>
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $catatan->judul }}</h3>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                                Dihapus {{ $catatan->updated_at?->diffForHumans() ?? '-' }} |
                                                Tanggal catatan: {{ \Illuminate\Support\Carbon::parse($catatan->tanggal)->translatedFormat('d M Y') }}
                                            </p>
                                            <p class="mt-3 text-sm text-gray-600 line-clamp-2 dark:text-slate-300">{{ $catatan->previewForDisplay() !== '' ? $catatan->previewForDisplay() : 'Preview disembunyikan' }}</p>
                                        </div>
                                        <div class="flex flex-col gap-2 text-sm">
                                            <form action="{{ route('catatan.restore', $catatan) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 px-4 py-2 font-semibold text-emerald-600 hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Pulihkan
                                                </button>
                                            </form>
                                            <form action="{{ route('catatan.force-delete', $catatan) }}" method="POST" onsubmit="return confirm('Hapus permanen catatan ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2 font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Hapus Permanen
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>
            <div class="mt-6">
                {{ $catatans->links() }}
            </div>
        @endif

        <form method="POST" action="{{ route('catatan.bulk-restore') }}" data-bulk-restore-form class="hidden">
            @csrf
            <input type="hidden" name="page" value="{{ request('page') }}">
        </form>

        <form method="POST" action="{{ route('catatan.bulk-force-delete') }}" data-bulk-delete-form class="hidden">
            @csrf
            <input type="hidden" name="page" value="{{ request('page') }}">
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const container = document.querySelector('[data-catatan-trash-manage]');
            if (!container) return;

            const selectAll = container.querySelector('[data-select-all]');
            const checkboxes = Array.from(container.querySelectorAll('[data-catatan-checkbox]'));
            const selectedCount = container.querySelector('[data-selected-count]');
            const restoreButton = container.querySelector('[data-bulk-restore]');
            const deleteButton = container.querySelector('[data-bulk-delete]');
            const restoreForm = document.querySelector('[data-bulk-restore-form]');
            const deleteForm = document.querySelector('[data-bulk-delete-form]');

            if (!selectAll || !checkboxes.length || !selectedCount || !restoreButton || !deleteButton || !restoreForm || !deleteForm) {
                return;
            }

            const selectedIds = () => checkboxes
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value);

            const fillAndSubmit = (form, ids) => {
                form.querySelectorAll('input[name=\"catatan_ids[]\"]').forEach((input) => input.remove());

                ids.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'catatan_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                form.submit();
            };

            const syncState = () => {
                const checked = selectedIds().length;
                selectedCount.textContent = String(checked);
                restoreButton.disabled = checked === 0;
                deleteButton.disabled = checked === 0;
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

            restoreButton.addEventListener('click', () => {
                const ids = selectedIds();
                if (!ids.length) return;
                fillAndSubmit(restoreForm, ids);
            });

            deleteButton.addEventListener('click', () => {
                const ids = selectedIds();
                if (!ids.length) return;
                if (!confirm('Hapus permanen semua catatan terpilih?')) return;
                fillAndSubmit(deleteForm, ids);
            });

            syncState();
        })();
    </script>
@endpush
