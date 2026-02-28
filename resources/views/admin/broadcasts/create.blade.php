{{-- resources/views/admin/broadcasts/create.blade.php --}}
@extends('layouts.app')

@section('page_title', 'Buat Broadcast')
@section('page_description', 'Tulis postingan/notifikasi untuk afiliasi target.')

@section('page_actions')
    <a href="{{ route('admin.broadcasts.index') }}"
       class="inline-flex items-center justify-center rounded-2xl border border-slate-200/80 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-indigo-200">
        Kembali
    </a>
@endsection

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @if (!empty($creationBlocked))
                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    Akun admin ini belum punya assignment afiliasi aktif. Minta super admin menetapkan afiliasi dulu.
                </div>
            @endif

            <form action="{{ route('admin.broadcasts.store') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="space-y-6">
                @csrf

                @include('admin.broadcasts._form', [
                    'targetOptions' => $targetOptions,
                    'canUseGlobal' => $canUseGlobal,
                    'selectedTargetValues' => old('targets', []),
                ])

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <a href="{{ route('admin.broadcasts.index') }}"
                       class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                        Batal
                    </a>
                    <button type="submit"
                            @disabled(!empty($creationBlocked))
                            class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        Simpan Draft
                    </button>
                    <button type="submit"
                            name="publish_now"
                            value="1"
                            @disabled(!empty($creationBlocked))
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                        Simpan & Publish
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
