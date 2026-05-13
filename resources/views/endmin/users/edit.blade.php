{{-- resources/views/endmin/users/edit.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Ubah Akun')
@section('page_description', 'Perbarui akun, status, dan afiliasi pengguna.')

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
        $canBanTarget = ! $isSelf && ! $targetIsSuperAdmin;
        $selectedTemplateId = old('affiliation_template_id', $user->affiliation_template_id);
        $selectedAffiliationType = old('affiliation_type', $user->affiliation_type);
        $selectedStudentIdType = old('student_id_type', $user->student_id_type);
        $affiliationStatus = old('affiliation_status', $user->affiliation_status ?: 'pending');
        $hasLegacyAffiliation = filled($user->affiliation_name) && blank($user->affiliation_template_id);
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500 dark:text-slate-400">Detail akun pengguna</p>
                    <h2 class="mt-1 break-words text-2xl font-semibold text-gray-900 dark:text-slate-100">
                        {{ $user->name ?: 'Tanpa nama' }}
                    </h2>
                    <p class="mt-1 break-all text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($user->isSuperAdmin())
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Super Admin</span>
                    @elseif ($user->isAdminOnly())
                        <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-100">Admin</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Pengguna</span>
                    @endif

                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $user->account_status === 'banned' ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-100' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-100' }}">
                        {{ $user->account_status === 'banned' ? 'Banned' : 'Active' }}
                    </span>
                </div>
            </div>

            @if ($hasLegacyAffiliation)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    User ini masih memakai afiliasi legacy. Pilih template afiliasi untuk mengikat akun ke master afiliasi.
                </div>
            @endif
        </div>

        <form action="{{ route('endmin.users.update', $user) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Akun</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Identitas Login</h3>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="name" class="form-label">Nama</label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', $user->name) }}"
                               class="mt-1 form-input"
                               required>
                        @error('name')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="form-label">Email</label>
                        <input type="email"
                               name="email"
                               id="email"
                               value="{{ old('email', $user->email) }}"
                               class="mt-1 form-input"
                               required>
                        @error('email')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <label class="mt-4 inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <input type="hidden" name="email_verified" value="0">
                    <input type="checkbox"
                           name="email_verified"
                           value="1"
                           class="endmin-checkbox"
                           @checked((bool) old('email_verified', $user->email_verified_at ? 1 : 0))>
                    Email sudah terverifikasi
                </label>
            </div>

            <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Afiliasi</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Template dan Identitas</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Template adalah sumber utama. Nama manual hanya fallback untuk data legacy atau template baru.</p>
                </div>

                <div class="mt-5">
                    <label for="affiliation_template_id" class="form-label">Template Afiliasi</label>
                    <select name="affiliation_template_id" id="affiliation_template_id" class="mt-1 form-input">
                        <option value="">- Belum memilih template / pakai manual -</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>
                                {{ strtoupper((string) ($template->affiliation_type ?: 'other')) }} - {{ $template->affiliation_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('affiliation_template_id')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="affiliation_type" class="form-label">Tipe Manual</label>
                        <select id="affiliation_type" name="affiliation_type" class="mt-1 form-input">
                            <option value="">- Pilih tipe -</option>
                            <option value="school" @selected($selectedAffiliationType === 'school')>Sekolah</option>
                            <option value="university" @selected($selectedAffiliationType === 'university')>Universitas</option>
                            <option value="polytechnic" @selected($selectedAffiliationType === 'polytechnic')>Politeknik</option>
                            <option value="organization" @selected($selectedAffiliationType === 'organization')>Organisasi</option>
                            <option value="company" @selected($selectedAffiliationType === 'company')>Perusahaan</option>
                            <option value="other" @selected($selectedAffiliationType === 'other')>Lainnya</option>
                        </select>
                        @error('affiliation_type')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="affiliation_name" class="form-label">Nama Manual</label>
                        <input type="text"
                               id="affiliation_name"
                               name="affiliation_name"
                               value="{{ old('affiliation_name', $user->affiliation_name) }}"
                               class="mt-1 form-input"
                               placeholder="Contoh: Universitas Indonesia">
                        @error('affiliation_name')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div>
                        <label for="affiliation_status" class="form-label">Status Afiliasi</label>
                        <select id="affiliation_status" name="affiliation_status" class="mt-1 form-input" required>
                            <option value="pending" @selected($affiliationStatus === 'pending')>Pending</option>
                            <option value="verified" @selected($affiliationStatus === 'verified')>Verified</option>
                            <option value="rejected" @selected($affiliationStatus === 'rejected')>Rejected</option>
                        </select>
                        @error('affiliation_status')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="student_id_type" class="form-label">Tipe Identitas</label>
                        <select id="student_id_type" name="student_id_type" class="mt-1 form-input">
                            <option value="">- Pilih tipe -</option>
                            <option value="nim" @selected($selectedStudentIdType === 'nim')>NIM</option>
                            <option value="nrp" @selected($selectedStudentIdType === 'nrp')>NRP</option>
                            <option value="nisn" @selected($selectedStudentIdType === 'nisn')>NISN</option>
                            <option value="nidn" @selected($selectedStudentIdType === 'nidn')>NIDN</option>
                            <option value="nip" @selected($selectedStudentIdType === 'nip')>NIP</option>
                            <option value="other" @selected($selectedStudentIdType === 'other')>Lainnya</option>
                        </select>
                        @error('student_id_type')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="student_id_number" class="form-label">Nomor Identitas</label>
                        <input type="text"
                               id="student_id_number"
                               name="student_id_number"
                               value="{{ old('student_id_number', $user->student_id_number) }}"
                               class="mt-1 form-input"
                               placeholder="Contoh: 2315110001">
                        @error('student_id_number')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($user->isAdminOnly())
                    <div class="mt-5 rounded-xl border border-slate-200/80 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Assignment Admin Aktif</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($user->adminAssignments as $assignment)
                                <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-100">
                                    {{ strtoupper((string) $assignment->affiliation_type) }} - {{ $assignment->affiliation_name }}
                                </span>
                            @empty
                                <span class="text-sm text-slate-500 dark:text-slate-400">Belum ada assignment aktif.</span>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Keamanan</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Password dan Status Akun</h3>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
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
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
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
                </div>

                @if (! $canEditPassword)
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Password akun super admin lain hanya dapat diubah oleh pemilik akun itu sendiri.
                    </p>
                @endif

                <div class="mt-5 rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Status Akun</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Akun sendiri dan sesama super admin tidak dapat dibanned.</p>

                    <div class="mt-3">
                        <label for="account_status" class="form-label">Status account</label>
                        <select name="account_status" id="account_status" class="mt-1 form-input" required>
                            <option value="active" @selected(old('account_status', $user->account_status) === 'active')>Active</option>
                            <option value="banned" @selected(old('account_status', $user->account_status) === 'banned') @disabled(! $canBanTarget)>Banned</option>
                        </select>
                        @error('account_status')
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                        @if (! $canBanTarget)
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ $isSelf ? 'Tidak bisa memblokir akun yang sedang digunakan.' : 'Sesama super admin tidak dapat saling membanned.' }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="ban_reason_code" class="form-label">Kode alasan ban</label>
                            <input type="text"
                                   name="ban_reason_code"
                                   id="ban_reason_code"
                                   value="{{ old('ban_reason_code', $user->ban_reason_code) }}"
                                   class="mt-1 form-input"
                                   placeholder="mis. abuse, spam, policy_violation">
                            @error('ban_reason_code')
                                <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="ban_reason_text" class="form-label">Catatan alasan ban</label>
                            <textarea name="ban_reason_text"
                                      id="ban_reason_text"
                                      rows="2"
                                      class="mt-1 form-input"
                                      placeholder="Catatan tambahan untuk audit internal...">{{ old('ban_reason_text', $user->ban_reason_text) }}</textarea>
                            @error('ban_reason_text')
                                <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('endmin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
                    Batal
                </a>
                <button type="submit"
                        class="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white shadow hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection
