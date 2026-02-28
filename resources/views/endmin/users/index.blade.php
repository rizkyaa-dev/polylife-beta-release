{{-- resources/views/endmin/users/index.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Manajemen Akun')
@section('page_description', 'Kelola seluruh akun pengguna dari panel super admin.')

@section('content')
@php
    $stats = $stats ?? [
        'total_users' => 0,
        'super_admins' => 0,
        'unverified_users' => 0,
    ];

    $filters = $filters ?? [
        'q' => '',
        'role' => '',
        'account_status' => '',
        'email_status' => '',
    ];
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Total Akun</p>
            <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $stats['total_users'] }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Super Admin</p>
            <p class="mt-2 text-3xl font-bold text-emerald-800 dark:text-emerald-100">{{ $stats['super_admins'] }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-200">Belum Verifikasi</p>
            <p class="mt-2 text-3xl font-bold text-amber-800 dark:text-amber-100">{{ $stats['unverified_users'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Daftar Pengguna</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">Optimasi query aktif: data dipaginasi dan difilter di server.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('endmin.verifications.index') }}"
                   class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    Verifikasi User
                </a>
                <a href="{{ route('endmin.broadcast-verifications.index') }}"
                   class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    Verifikasi Broadcast
                </a>
                <a href="{{ route('endmin.audit-logs.index') }}"
                   class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    Audit Log
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('endmin.users.index') }}" class="mb-5 grid gap-3 md:grid-cols-5">
            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari</label>
                <input type="text"
                       name="q"
                       value="{{ $filters['q'] }}"
                       placeholder="Nama, email, afiliasi, student ID"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Peran</label>
                <select name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="super_admin" @selected($filters['role'] === 'super_admin')>Super Admin</option>
                    <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                    <option value="user" @selected($filters['role'] === 'user')>Pengguna</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status Akun</label>
                <select name="account_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="active" @selected($filters['account_status'] === 'active')>Aktif</option>
                    <option value="banned" @selected($filters['account_status'] === 'banned')>Banned</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</label>
                <select name="email_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua</option>
                    <option value="verified" @selected($filters['email_status'] === 'verified')>Terverifikasi</option>
                    <option value="unverified" @selected($filters['email_status'] === 'unverified')>Belum</option>
                </select>
            </div>
            <div class="md:col-span-5 flex items-center justify-end gap-2">
                <a href="{{ route('endmin.users.index') }}"
                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300">
                    Reset
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Terapkan Filter
                </button>
            </div>
        </form>

        @error('bulk')
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
                {{ $message }}
            </div>
        @enderror

        <form id="bulk-users-form" action="{{ route('endmin.users.bulk') }}" method="POST" class="mb-4">
            @csrf
            <div id="bulk-users-hidden-inputs"></div>

            <div class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-900/70 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input id="select-all-users"
                               type="checkbox"
                               class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Pilih semua di halaman ini
                    </label>
                    <span id="selected-users-count" class="text-xs font-semibold text-slate-500 dark:text-slate-400">0 dipilih</span>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select name="action"
                            required
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        <option value="">Pilih bulk action</option>
                        <option value="verify_email">Verifikasi Email</option>
                        <option value="activate_accounts">Aktifkan Akun</option>
                        <option value="ban_accounts">Ban Akun</option>
                        <option value="promote_to_admin">Jadikan Admin</option>
                        <option value="demote_to_user">Turunkan ke Pengguna</option>
                        <option value="delete_accounts">Hapus Akun</option>
                    </select>
                    <input type="text"
                           name="reason"
                           placeholder="Alasan (opsional, untuk ban)"
                           class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        Proses Terpilih
                    </button>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-100 dark:border-slate-800">
                        <th class="py-3 pr-3 w-10"></th>
                        <th class="py-3 pr-4">Nama</th>
                        <th class="py-3 pr-4">Email</th>
                        <th class="py-3 pr-4">Peran</th>
                        <th class="py-3 pr-4">Status</th>
                        <th class="py-3 pr-4">Verifikasi</th>
                        <th class="py-3 pr-4">Terdaftar</th>
                        <th class="py-3 pl-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($users as $user)
                        @php
                            $isSelf = auth()->id() === $user->id;
                        @endphp
                        <tr>
                            <td class="py-3 pr-3">
                                <input type="checkbox"
                                       class="bulk-user-checkbox h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                       value="{{ $user->id }}">
                            </td>
                            <td class="py-3 pr-4 font-medium text-slate-900 dark:text-slate-100">
                                {{ $user->name ?: '-' }}
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300 break-all">
                                {{ $user->email }}
                            </td>
                            <td class="py-3 pr-4">
                                @if ($user->isSuperAdmin())
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Super Admin</span>
                                @elseif ($user->isAdminOnly())
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" style="background-color:#dbeafe;color:#1d4ed8;">Admin</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Pengguna</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if (($user->account_status ?? 'active') === 'banned')
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-100">Banned</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Aktif</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if ($user->email_verified_at)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100">Terverifikasi</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-100">Belum</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                {{ optional($user->created_at)->format('Y-m-d') }}
                            </td>
                            <td class="py-3 pl-4">
                                <div class="flex items-center justify-end gap-2">
                                    @if (! $user->email_verified_at)
                                        <form action="{{ route('endmin.users.verify', $user) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-100 dark:hover:bg-emerald-500/20">
                                                Verifikasi
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('endmin.users.edit', $user) }}"
                                       class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-100 dark:hover:bg-indigo-500/20">
                                        Edit
                                    </a>
                                    @if ($isSelf)
                                        <span class="text-xs text-slate-400">Akun aktif</span>
                                    @else
                                        <form action="{{ route('endmin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Hapus akun ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-100 dark:hover:bg-rose-500/20">
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-gray-500 dark:text-slate-400">Belum ada akun.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const bulkForm = document.getElementById('bulk-users-form');
        if (!bulkForm) {
            return;
        }

        const selectAll = document.getElementById('select-all-users');
        const selectedCount = document.getElementById('selected-users-count');
        const hiddenContainer = document.getElementById('bulk-users-hidden-inputs');
        const rowCheckboxes = Array.from(document.querySelectorAll('.bulk-user-checkbox'));
        const actionSelect = bulkForm.querySelector('select[name="action"]');

        const syncSelectedCount = () => {
            const count = rowCheckboxes.filter((checkbox) => checkbox.checked).length;
            selectedCount.textContent = `${count} dipilih`;
        };

        const syncSelectAll = () => {
            if (!selectAll) {
                return;
            }

            const activeRows = rowCheckboxes.filter((checkbox) => !checkbox.disabled);
            if (activeRows.length === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
                return;
            }

            const checkedRows = activeRows.filter((checkbox) => checkbox.checked).length;
            selectAll.checked = checkedRows === activeRows.length;
            selectAll.indeterminate = checkedRows > 0 && checkedRows < activeRows.length;
        };

        const rebuildHiddenInputs = () => {
            hiddenContainer.innerHTML = '';

            rowCheckboxes
                .filter((checkbox) => checkbox.checked)
                .forEach((checkbox) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'user_ids[]';
                    input.value = checkbox.value;
                    hiddenContainer.appendChild(input);
                });
        };

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                rowCheckboxes.forEach((checkbox) => {
                    if (!checkbox.disabled) {
                        checkbox.checked = selectAll.checked;
                    }
                });
                syncSelectedCount();
                syncSelectAll();
            });
        }

        rowCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                syncSelectedCount();
                syncSelectAll();
            });
        });

        bulkForm.addEventListener('submit', (event) => {
            rebuildHiddenInputs();

            const selectedInputs = hiddenContainer.querySelectorAll('input[name="user_ids[]"]').length;
            if (selectedInputs === 0) {
                event.preventDefault();
                alert('Pilih minimal satu akun terlebih dahulu.');
                return;
            }

            if (!actionSelect || actionSelect.value === '') {
                event.preventDefault();
                alert('Pilih aksi bulk terlebih dahulu.');
                return;
            }

            if (actionSelect.value === 'delete_accounts') {
                const confirmed = confirm('Hapus akun terpilih? Aksi ini tidak bisa dibatalkan.');
                if (!confirmed) {
                    event.preventDefault();
                }
            }
        });

        syncSelectedCount();
        syncSelectAll();
    })();
</script>
@endpush
