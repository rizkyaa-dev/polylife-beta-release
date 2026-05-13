{{-- resources/views/endmin/affiliations/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Master Afiliasi')
@section('page_description', 'Ringkasan afiliasi user untuk validasi operasional admin dan verifikasi.')

@section('content')
@once
    @push('styles')
        <style>
            .affiliation-review-summary {
                grid-template-columns: minmax(5.75rem, 0.75fr) minmax(0, 1.7fr) minmax(5.25rem, 0.65fr);
            }

            .affiliation-review-actions {
                grid-template-columns: minmax(0, 1fr) minmax(13rem, 0.36fr);
            }

            .affiliation-approve-form {
                grid-template-columns: minmax(9rem, 0.85fr) minmax(7rem, 0.45fr) minmax(10rem, 0.8fr) 3.25rem;
            }

            .affiliation-reject-form {
                grid-template-columns: minmax(0, 1fr) 3.5rem;
            }

            @media (max-width: 900px) {
                .affiliation-review-actions {
                    grid-template-columns: 1fr;
                }

                .affiliation-approve-form {
                    grid-template-columns: minmax(8rem, 0.8fr) minmax(7rem, 0.45fr) minmax(10rem, 1fr) 3.25rem;
                }
            }

            @media (max-width: 640px) {
                .affiliation-review-summary {
                    grid-template-columns: minmax(5rem, 0.8fr) minmax(0, 1.4fr);
                }

                .affiliation-review-identity {
                    grid-column: 1 / -1;
                }

                .affiliation-approve-form,
                .affiliation-reject-form {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    @endpush
@endonce

<div class="space-y-6">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Pengajuan Afiliasi</p>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Menunggu Review</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">Setujui pengajuan user dan normalisasi nama afiliasi ke master template.</p>
            </div>
            <a href="{{ route('endmin.affiliations.manage.index') }}"
               class="inline-flex items-center justify-center rounded-xl border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-200 dark:hover:bg-indigo-500/10">
                Manage
            </a>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($pendingRequests as $request)
                @php
                    $suggestedTemplates = $templateSuggestions[$request->id] ?? collect();
                    $suggestedTemplateIds = $suggestedTemplates->pluck('id')->all();
                    $requestedTypeLabel = $affiliationTypeOptions[$request->affiliation_type] ?? ($request->affiliation_type ?: '-');
                @endphp
                <div class="rounded-2xl border border-slate-200 p-2.5 dark:border-slate-700">
                    <div class="affiliation-review-summary grid gap-2 text-xs">
                        <div class="affiliation-review-identity min-w-0">
                            <p class="text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">User</p>
                            <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $request->user?->name ?: 'User' }}</p>
                            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $request->user?->email }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">Afiliasi</p>
                            <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $request->affiliation_name }}</p>
                            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">Tipe diajukan: {{ $requestedTypeLabel }} &middot; {{ $request->created_at?->diffForHumans() }}</p>
                            @if ($suggestedTemplates->isNotEmpty())
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($suggestedTemplates as $suggestion)
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-200">
                                            Saran: {{ $affiliationTypeOptions[$suggestion->affiliation_type] ?? ($suggestion->affiliation_type ?: '-') }} - {{ $suggestion->affiliation_name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase text-slate-500 dark:text-slate-400">Identitas</p>
                            <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ strtoupper((string) ($request->student_id_type ?: 'ID')) }}</p>
                            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $request->student_id_number ?: '-' }}</p>
                        </div>
                    </div>

                    <div class="affiliation-review-actions mt-2 grid gap-1.5">
                        <form method="POST" action="{{ route('endmin.affiliations.requests.approve', $request) }}" class="affiliation-approve-form grid gap-1.5">
                            @csrf
                            @method('PATCH')
                            <select name="affiliation_template_id" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" aria-label="Gunakan template">
                                <option value="">Buat/pakai nama final</option>
                                @if ($suggestedTemplates->isNotEmpty())
                                    <optgroup label="Saran paling mirip">
                                        @foreach ($suggestedTemplates as $template)
                                            <option value="{{ $template->id }}">{{ $template->affiliation_name }}{{ $template->affiliation_type ? ' - '.($affiliationTypeOptions[$template->affiliation_type] ?? $template->affiliation_type) : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @foreach ($templates as $template)
                                    @continue(in_array($template->id, $suggestedTemplateIds, true))
                                    <option value="{{ $template->id }}">{{ $template->affiliation_name }}{{ $template->affiliation_type ? ' - '.($affiliationTypeOptions[$template->affiliation_type] ?? $template->affiliation_type) : '' }}</option>
                                @endforeach
                            </select>
                            <select name="canonical_affiliation_type" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" aria-label="Tipe afiliasi final">
                                @foreach ($affiliationTypeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($request->affiliation_type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text"
                                   name="canonical_affiliation_name"
                                   value="{{ $request->affiliation_name }}"
                                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                   aria-label="Nama afiliasi final">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                                ACC
                            </button>
                        </form>

                        <form method="POST" action="{{ route('endmin.affiliations.requests.reject', $request) }}" class="affiliation-reject-form grid gap-1.5">
                            @csrf
                            @method('PATCH')
                            <input type="text"
                                   name="rejection_reason"
                                   placeholder="Alasan penolakan"
                                   class="min-w-0 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-200 dark:hover:bg-rose-500/10">
                                Tolak
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    Tidak ada pengajuan afiliasi pending.
                </div>
            @endforelse
        </div>

        @if ($pendingRequests->hasPages())
            <nav class="mt-4 flex justify-center" aria-label="Pagination pengajuan afiliasi">
                <div class="inline-flex overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    @if ($pendingRequests->onFirstPage())
                        <span class="inline-flex h-10 w-10 cursor-not-allowed items-center justify-center border-r border-slate-200 text-slate-300 dark:border-slate-700 dark:text-slate-600" aria-disabled="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L9.06 10l3.71 3.71a.75.75 0 1 1-1.06 1.06l-4.24-4.24a.75.75 0 0 1 0-1.06l4.24-4.24a.75.75 0 0 1 1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @else
                        <a href="{{ $pendingRequests->previousPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center border-r border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" rel="prev" aria-label="Halaman sebelumnya">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L9.06 10l3.71 3.71a.75.75 0 1 1-1.06 1.06l-4.24-4.24a.75.75 0 0 1 0-1.06l4.24-4.24a.75.75 0 0 1 1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    @foreach ($pendingRequests->getUrlRange(1, $pendingRequests->lastPage()) as $page => $url)
                        @if ($page === $pendingRequests->currentPage())
                            <span class="inline-flex h-10 min-w-10 items-center justify-center border-r border-slate-200 bg-slate-100 px-4 text-sm font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex h-10 min-w-10 items-center justify-center border-r border-slate-200 px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($pendingRequests->hasMorePages())
                        <a href="{{ $pendingRequests->nextPageUrl() }}" class="inline-flex h-10 w-10 items-center justify-center text-slate-500 transition hover:bg-slate-50 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" rel="next" aria-label="Halaman berikutnya">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span class="inline-flex h-10 w-10 cursor-not-allowed items-center justify-center text-slate-300 dark:text-slate-600" aria-disabled="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @endif
                </div>
            </nav>
        @endif
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('endmin.affiliations.index') }}" class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama afiliasi atau tipe"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status User</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                    <option value="verified" @selected($filters['status'] === 'verified')>Verified</option>
                    <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                </select>
            </div>
            <div class="md:col-span-3 flex items-center justify-end gap-2">
                <a href="{{ route('endmin.affiliations.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left dark:border-slate-800">
                        <th class="py-3 pr-4">Tipe</th>
                        <th class="py-3 pr-4">Nama Afiliasi</th>
                        <th class="py-3 pr-4">Total User</th>
                        <th class="py-3 pr-4">Verified</th>
                        <th class="py-3 pr-4">Pending</th>
                        <th class="py-3 pr-4">Admin</th>
                        <th class="py-3 pr-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($affiliations as $affiliation)
                        <tr>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $affiliation->affiliation_type ?: '-' }}</td>
                            <td class="py-3 pr-4 font-medium text-slate-900 dark:text-slate-100">{{ $affiliation->affiliation_name }}</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ (int) $affiliation->total_users }}</td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">
                                    {{ (int) $affiliation->verified_users }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">
                                    {{ (int) $affiliation->pending_users }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-100">
                                    {{ (int) $affiliation->admin_count }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <a href="{{ route('endmin.affiliations.extend', ['affiliationName' => $affiliation->affiliation_name, 'type' => $affiliation->affiliation_type]) }}"
                                   class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-200 dark:hover:bg-indigo-500/10">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data afiliasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $affiliations->links() }}
        </div>
    </div>
</div>
@endsection
