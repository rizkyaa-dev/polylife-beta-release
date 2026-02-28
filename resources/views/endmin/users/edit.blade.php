{{-- resources/views/endmin/users/edit.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Ubah Akun')
@section('page_description', 'Perbarui email dan reset password pengguna.')

@section('page_actions')
    <a href="{{ route('endmin.users.index') }}"
       class="inline-flex items-center justify-center rounded-2xl border border-slate-200/80 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-indigo-200">
        Kembali
    </a>
@endsection

@section('content')
    @php
        $isSelf = auth()->id() === $user->id;
        $targetIsSuperAdmin = $user->isSuperAdmin();
        $canEditPassword = !($targetIsSuperAdmin && !$isSelf);
        $canBanTarget = !($targetIsSuperAdmin && !$isSelf);
    @endphp

    <div class="max-w-3xl mx-auto space-y-6">
        <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Detail akun pengguna</p>
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">
                        {{ $user->name ?: 'Tanpa nama' }}
                    </h2>
                </div>
                <div>
                    @if ($user->isSuperAdmin())
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Super Admin</span>
                    @elseif ($user->isAdminOnly())
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" style="background-color:#dbeafe;color:#1d4ed8;">Admin</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Pengguna</span>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
                <span>Email: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $user->email }}</span></span>
                <span>Status: <span class="font-medium text-slate-700 dark:text-slate-200">
                    {{ $user->email_verified_at ? 'Terverifikasi' : 'Belum verifikasi' }}
                </span></span>
                <span>Account: <span class="font-medium {{ $user->account_status === 'banned' ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                    {{ $user->account_status === 'banned' ? 'Banned' : 'Active' }}
                </span></span>
            </div>
        </div>

        <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
            <form action="{{ route('endmin.users.update', $user) }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="email" class="form-label">Email</label>
                    <input type="email"
                           name="email"
                           id="email"
                           value="{{ old('email', $user->email) }}"
                           class="mt-1 form-input"
                           required>
                    @error('email')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="form-label">Password baru</label>
                    <input type="password"
                           name="password"
                           id="password"
                           class="mt-1 form-input"
                           autocomplete="new-password"
                           @disabled(! $canEditPassword)
                           placeholder="{{ $canEditPassword ? 'Biarkan kosong jika tidak ingin mengubah' : 'Password super admin lain tidak dapat diubah' }}">
                    @error('password')
                        <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                    @if (! $canEditPassword)
                        <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">
                            Password akun super admin lain hanya dapat diubah oleh pemilik akun itu sendiri.
                        </p>
                    @endif
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Konfirmasi password</label>
                    <input type="password"
                           name="password_confirmation"
                           id="password_confirmation"
                           class="mt-1 form-input"
                           autocomplete="new-password"
                           @disabled(! $canEditPassword)>
                </div>

                <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Status Akun</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Hanya super admin yang bisa mengubah status ini.</p>

                    <div class="mt-3">
                        <label for="account_status" class="form-label">Status account</label>
                        <select name="account_status" id="account_status" class="mt-1 form-input" required>
                            <option value="active" @selected(old('account_status', $user->account_status) === 'active')>Active</option>
                            <option value="banned" @selected(old('account_status', $user->account_status) === 'banned') @disabled(! $canBanTarget)>Banned</option>
                        </select>
                        @error('account_status')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                        @if (! $canBanTarget)
                            <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">
                                Sesama super admin tidak dapat saling membanned.
                            </p>
                        @endif
                    </div>

                    <div class="mt-3">
                        <label for="ban_reason_code" class="form-label">Kode alasan ban</label>
                        <input type="text"
                               name="ban_reason_code"
                               id="ban_reason_code"
                               value="{{ old('ban_reason_code', $user->ban_reason_code) }}"
                               class="mt-1 form-input"
                               placeholder="mis. abuse, spam, policy_violation">
                        @error('ban_reason_code')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-3">
                        <label for="ban_reason_text" class="form-label">Catatan alasan ban</label>
                        <textarea name="ban_reason_text"
                                  id="ban_reason_text"
                                  rows="3"
                                  class="mt-1 form-input"
                                  placeholder="Catatan tambahan untuk audit internal...">{{ old('ban_reason_text', $user->ban_reason_text) }}</textarea>
                        @error('ban_reason_text')
                            <p class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('endmin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold shadow hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
