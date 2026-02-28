{{-- resources/views/endmin/verifications/edit.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Edit Verifikasi User')
@section('page_description', 'Pengaturan verifikasi email, afiliasi, dan identitas user secara detail.')

@section('page_actions')
    <a href="{{ route('endmin.verifications.index') }}"
       class="inline-flex items-center justify-center rounded-2xl border border-slate-200/80 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-indigo-200">
        Kembali
    </a>
@endsection

@section('content')
@php
    $affiliationStatus = strtolower(trim((string) old('affiliation_status', $user->affiliation_status ?? 'pending')));
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-slate-400">User yang sedang diedit</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">
                    {{ $user->name ?: 'Tanpa nama' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 break-all">{{ $user->email }}</p>
            </div>
            <div>
                @if ($user->isSuperAdmin())
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-100">Super Admin</span>
                @elseif ($user->isAdminOnly())
                    <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-100">Admin</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200">Pengguna</span>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white border rounded-2xl shadow-sm p-6 dark:bg-slate-900 dark:border-slate-800">
        <form action="{{ route('endmin.verifications.detail-update', $user) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="name" class="form-label">Nama</label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name', $user->name) }}"
                           class="mt-1 form-input"
                           required>
                    @error('name')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="form-label">Email</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email', $user->email) }}"
                           class="mt-1 form-input"
                           required>
                    @error('email')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Verifikasi Email</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Atur status email verified untuk login dan notifikasi.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox"
                           name="email_verified"
                           value="1"
                           class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           @checked((bool) old('email_verified', $user->email_verified_at ? 1 : 0))>
                    Email sudah terverifikasi
                </label>
            </div>

            <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Data Afiliasi & Identitas</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Data ini dipakai untuk validasi institusi dan identitas mahasiswa.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="affiliation_type" class="form-label">Tipe Afiliasi</label>
                        <select id="affiliation_type" name="affiliation_type" class="mt-1 form-input">
                            @php
                                $selectedAffiliationType = old('affiliation_type', $user->affiliation_type);
                            @endphp
                            <option value="">- Pilih tipe -</option>
                            <option value="school" @selected($selectedAffiliationType === 'school')>Sekolah</option>
                            <option value="university" @selected($selectedAffiliationType === 'university')>Universitas</option>
                            <option value="polytechnic" @selected($selectedAffiliationType === 'polytechnic')>Politeknik</option>
                            <option value="organization" @selected($selectedAffiliationType === 'organization')>Organisasi</option>
                            <option value="other" @selected($selectedAffiliationType === 'other')>Lainnya</option>
                        </select>
                        @error('affiliation_type')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="affiliation_name" class="form-label">Nama Afiliasi</label>
                        <input type="text"
                               id="affiliation_name"
                               name="affiliation_name"
                               value="{{ old('affiliation_name', $user->affiliation_name) }}"
                               class="mt-1 form-input"
                               placeholder="Contoh: Politeknik Negeri Jakarta">
                        @error('affiliation_name')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="student_id_type" class="form-label">Tipe ID Mahasiswa</label>
                        <select id="student_id_type" name="student_id_type" class="mt-1 form-input">
                            @php
                                $selectedStudentIdType = old('student_id_type', $user->student_id_type);
                            @endphp
                            <option value="">- Pilih tipe -</option>
                            <option value="nim" @selected($selectedStudentIdType === 'nim')>NIM</option>
                            <option value="nrp" @selected($selectedStudentIdType === 'nrp')>NRP</option>
                            <option value="nisn" @selected($selectedStudentIdType === 'nisn')>NISN</option>
                            <option value="nis" @selected($selectedStudentIdType === 'nis')>NIS</option>
                            <option value="other" @selected($selectedStudentIdType === 'other')>Lainnya</option>
                        </select>
                        @error('student_id_type')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="student_id_number" class="form-label">Nomor ID Mahasiswa</label>
                        <input type="text"
                               id="student_id_number"
                               name="student_id_number"
                               value="{{ old('student_id_number', $user->student_id_number) }}"
                               class="mt-1 form-input"
                               placeholder="Contoh: 2315110001">
                        @error('student_id_number')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-700 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Status Verifikasi Afiliasi</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Status verified akan menyimpan waktu verifikasi dan petugas verifikator.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label for="affiliation_status" class="form-label">Status</label>
                        <select id="affiliation_status" name="affiliation_status" class="mt-1 form-input" required>
                            <option value="pending" @selected($affiliationStatus === 'pending')>Pending</option>
                            <option value="verified" @selected($affiliationStatus === 'verified')>Verified</option>
                            <option value="rejected" @selected($affiliationStatus === 'rejected')>Rejected</option>
                        </select>
                        @error('affiliation_status')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="affiliation_verified_at" class="form-label">Waktu Verifikasi</label>
                        <input type="datetime-local"
                               id="affiliation_verified_at"
                               name="affiliation_verified_at"
                               value="{{ old('affiliation_verified_at', optional($user->affiliation_verified_at)->format('Y-m-d\\TH:i')) }}"
                               class="mt-1 form-input">
                        @error('affiliation_verified_at')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="affiliation_verified_by" class="form-label">Diverifikasi Oleh</label>
                        <select id="affiliation_verified_by" name="affiliation_verified_by" class="mt-1 form-input">
                            <option value="">- Otomatis (akun login) -</option>
                            @foreach ($superAdmins as $superAdmin)
                                <option value="{{ $superAdmin->id }}" @selected((string) old('affiliation_verified_by', $user->affiliation_verified_by) === (string) $superAdmin->id)>
                                    {{ $superAdmin->name ?: $superAdmin->email }} ({{ $superAdmin->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('affiliation_verified_by')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('endmin.verifications.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">
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
