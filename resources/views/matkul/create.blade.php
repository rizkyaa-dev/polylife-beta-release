@extends('layouts.app')

@section('page_title', 'Tambah Matkul')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8 space-y-6 relative dark:bg-slate-900 dark:border-slate-800">
            @if ($errors->any())
                <div class="popup-overlay" data-popup>
                    <div class="popup-card">
                        <div class="popup-icon">!</div>
                        <div class="popup-body">
                            <h3>Ups, data belum lengkap</h3>
                            <p>Data matkul harus diisi dan tidak boleh kosong. Periksa kembali form di bawah ini.</p>
                        </div>
                        <button type="button" class="popup-close" data-popup-close>OK</button>
                    </div>
                </div>
            @endif
            <div>
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold dark:text-indigo-300">Data Akademik</p>
                <h2 class="text-2xl font-bold text-gray-900 mt-1 dark:text-slate-100">Tambah Mata Kuliah</h2>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500 dark:text-slate-400">
                        Simpan detail matkul agar bisa dipakai ulang saat membuat jadwal maupun kegiatan.
                    </p>
                    <a href="{{ route('matkul.batch') }}"
                       class="inline-flex items-center rounded-2xl border border-dashed border-indigo-300 px-4 py-2 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:border-indigo-500/40 dark:text-indigo-200 dark:hover:bg-slate-800">
                        Batch Process (Beta)
                    </a>
                </div>
            </div>

            @include('matkul.partials.form', [
                'action' => route('matkul.store'),
                'method' => 'POST',
                'matkul' => null,
                'submitLabel' => 'Simpan Matkul',
            ])
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.4s ease;
            z-index: 40;
        }
        .popup-overlay.popup-show {
            opacity: 1;
            pointer-events: auto;
        }
        .popup-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            width: min(420px, calc(100% - 2rem));
            box-shadow: 0 20px 45px rgba(79,70,229,0.25);
            transform: translateY(20px) scale(0.96);
            animation: popupBounce 0.5s ease forwards;
        }
        :is(.dark) .popup-card {
            background: #0f172a;
            color: #e2e8f0;
            box-shadow: 0 20px 45px rgba(15,23,42,0.6);
        }
        @keyframes popupBounce {
            0% { transform: translateY(20px) scale(0.96); opacity: 0; }
            60% { transform: translateY(-6px) scale(1.02); opacity: 1; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        .popup-icon {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            background: linear-gradient(135deg, #f43f5e, #f97316);
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
            display: grid;
            place-items: center;
            margin-bottom: 1rem;
            box-shadow: 0 10px 20px rgba(244,63,94,0.3);
        }
        .popup-body h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #111827;
        }
        :is(.dark) .popup-body h3 {
            color: #f8fafc;
        }
        .popup-body p {
            margin: 0.5rem 0 0;
            font-size: 0.95rem;
            color: #4b5563;
        }
        :is(.dark) .popup-body p {
            color: #cbd5f5;
        }
        .popup-close {
            margin-top: 1.5rem;
            width: 100%;
            border: none;
            border-radius: 999px;
            padding: 0.85rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .popup-close:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(99,102,241,0.35);
        }
        :is(.dark) .popup-close {
            background: linear-gradient(135deg, #4c1d95, #2563eb);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const popup = document.querySelector('[data-popup]');
            if (!popup) return;
            popup.classList.add('popup-show');
            const closePopup = () => popup.classList.remove('popup-show');
            document.querySelector('[data-popup-close]')?.addEventListener('click', closePopup);
            popup.addEventListener('click', (event) => {
                if (event.target === popup) closePopup();
            });
        });
    </script>
@endpush
