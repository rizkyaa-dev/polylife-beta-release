{{-- resources/views/endmin/affiliations/extend.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Detail Afiliasi')
@section('page_description', 'Daftar akun dan admin dalam satu afiliasi.')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Detail Afiliasi</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $affiliationName }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $affiliationType ?: 'Tipe tidak diisi' }}</p>
        </div>
        <a href="{{ route('endmin.affiliations.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
            Kembali
        </a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Total Akun</p>
            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ (int) $summary['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Verified</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-300">{{ (int) $summary['verified'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Pending</p>
            <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-300">{{ (int) $summary['pending'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Admin</p>
            <p class="mt-1 text-2xl font-bold text-sky-600 dark:text-sky-300">{{ (int) $summary['admin'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Super Admin</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-300">{{ (int) $summary['super_admin'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('endmin.affiliations.extend', ['affiliationName' => $affiliationName, 'type' => $affiliationType]) }}" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_11rem_11rem_8rem_auto]">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama, email, nomor identitas"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                    <option value="verified" @selected($filters['status'] === 'verified')>Verified</option>
                    <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Role</label>
                <select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="user" @selected($filters['role'] === 'user')>User</option>
                    <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                    <option value="super_admin" @selected($filters['role'] === 'super_admin')>Super Admin</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Per page</label>
                <select name="per_page" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    @foreach ([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <a href="{{ route('endmin.affiliations.extend', ['affiliationName' => $affiliationName, 'type' => $affiliationType]) }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">
                    Filter
                </button>
            </div>
        </form>

        <form method="POST"
              action="{{ route('endmin.affiliations.extend.batch', ['affiliationName' => $affiliationName, 'type' => $affiliationType]) }}"
              class="mt-5"
              onsubmit="return confirm('Proses akun terpilih?');">
            @csrf

            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <input type="checkbox" data-select-all-users class="endmin-checkbox">
                    Pilih semua di halaman ini
                </label>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" name="action" value="verify" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">
                        Verifikasi Terpilih
                    </button>
                    <button type="submit" name="action" value="unverify" class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-500/40 dark:text-amber-200 dark:hover:bg-amber-500/10">
                        Batalkan Verifikasi
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100 dark:border-slate-800">
                <table class="min-w-[760px] w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-slate-50/70 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                            <th class="w-16 px-4 py-3"></th>
                            <th class="py-3 pr-4">Akun</th>
                            <th class="py-3 pr-4">Role</th>
                            <th class="py-3 pr-4">Status Afiliasi</th>
                            <th class="py-3 pr-4">Identitas</th>
                            <th class="py-3 pr-4">Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse ($users as $user)
                            <tr class="cursor-pointer align-top transition hover:bg-slate-50/70 dark:hover:bg-slate-950/40" data-user-row>
                                <td class="px-4 py-4">
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" data-user-checkbox class="endmin-checkbox" aria-label="Pilih {{ $user->name }}">
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-indigo-50 text-sm font-bold uppercase text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-100">
                                            {{ mb_substr($user->name ?: $user->email, 0, 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $user->name }}</p>
                                            <p class="mt-0.5 break-all text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $user->roleLabel() }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4">
                                    @php
                                        $statusClass = match ($user->affiliation_status) {
                                            'verified' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100',
                                            'rejected' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-100',
                                            default => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-100',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">
                                        {{ $user->affiliation_status ?: 'pending' }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                    <p class="font-semibold">{{ strtoupper((string) ($user->student_id_type ?: 'ID')) }}</p>
                                    <p class="text-xs">{{ $user->student_id_number ?: '-' }}</p>
                                </td>
                                <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                    <p>{{ $user->affiliation_verified_at?->format('Y-m-d H:i') ?: '-' }}</p>
                                    @if ($user->affiliation_verified_by)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">By #{{ $user->affiliation_verified_by }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-500 dark:text-slate-400">Tidak ada akun sesuai filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        (() => {
            const selectAll = document.querySelector('[data-select-all-users]');
            const checkboxes = [...document.querySelectorAll('[data-user-checkbox]')];
            if (!selectAll || checkboxes.length === 0) return;

            selectAll.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
            });

            document.querySelectorAll('[data-user-row]').forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('a, button, input, select, textarea, label')) {
                        return;
                    }

                    const checkbox = row.querySelector('[data-user-checkbox]');
                    if (!checkbox) {
                        return;
                    }

                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        })();
    </script>
@endpush
