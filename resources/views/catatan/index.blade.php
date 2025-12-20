@extends('layouts.app')

@section('page_title', 'Catatan')

@section('content')
    @php
        $guestMode = $guestMode ?? false;
    @endphp
    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm text-gray-500">Simpan ide, ringkasan, atau hal penting lainnya</p>
                <h2 class="text-2xl font-semibold text-gray-900">Catatan Pribadi</h2>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('catatan.sampah') }}" @endif
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold {{ $guestMode ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:border-gray-300' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2 2 0 0116.166 21H7.834a2 2 0 01-1.994-1.327L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0V4.5a2.25 2.25 0 00-2.25-2.25h-3a2.25 2.25 0 00-2.25 2.25v.563m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Sampah ({{ $trashCount }})
                </a>
                <a @if($guestMode) aria-disabled="true" @else href="{{ route('catatan.create') }}" @endif
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-sm font-semibold shadow-sm {{ $guestMode ? 'bg-gray-300 cursor-not-allowed text-gray-600' : '' }}"
                   style="{{ $guestMode ? '' : 'background-color: #1261DE;' }}">
                    {{ $guestMode ? 'Mode baca' : '+ Catatan Baru' }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($guestMode)
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-900">
                Mode tamu menonaktifkan edit & hapus. Data contoh bisa disesuaikan lewat <code>storage/app/guest/workspace.json</code>.
            </div>
        @endif

        @if ($catatans->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center text-gray-500">
                Belum ada catatan. Mulai tulis hal penting dengan klik tombol <span class="font-semibold">+ Catatan Baru</span>.
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($catatans as $catatan)
                    <article data-catatan-card tabindex="0"
                             class="flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-lg focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:outline-none">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-indigo-600 font-semibold">
                                {{ \Illuminate\Support\Carbon::parse($catatan->tanggal)->translatedFormat('d M Y') }}
                            </span>
                            <button type="button"
                                    class="inline-flex items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 p-2 text-indigo-600 transition hover:border-indigo-200 hover:text-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-300 focus-visible:outline-none"
                                    data-catatan-toggle
                                    aria-label="Perbesar catatan"
                                    aria-pressed="false"
                                    aria-expanded="false">
                                <svg data-icon="expand" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4h6m0 0V4m0 0L4 10m16 10h-6m0 0v0m0 0 6-6" />
                                </svg>
                                <svg data-icon="collapse" xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M12 5v14" />
                                </svg>
                            </button>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900 line-clamp-2 catatan-title">{{ $catatan->judul }}</h3>
                        <div class="mt-2 space-y-2 text-sm text-gray-600">
                            <p class="line-clamp-3 catatan-preview">
                                {{ \Illuminate\Support\Str::limit(strip_tags($catatan->isi), 180) }}
                            </p>
                            <div class="hidden catatan-full whitespace-pre-line leading-relaxed text-gray-700">
                                {{ $catatan->isi }}
                            </div>
                        </div>
                        @unless($guestMode)
                            <div class="mt-4 flex items-center justify-between text-sm">
                                <a href="{{ route('catatan.edit', $catatan) }}"
                                   class="inline-flex items-center gap-1 text-indigo-600 font-semibold hover:text-indigo-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036l-9.193 9.193a2 2 0 00-.512.878l-.708 2.829 2.829-.707a2 2 0 00.878-.512l9.193-9.193a1.5 1.5 0 000-2.121z" />
                                    </svg>
                                    Edit
                                </a>
                                <form action="{{ route('catatan.destroy', $catatan) }}" method="POST" onsubmit="return confirm('Pindahkan catatan ke sampah?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-rose-600 hover:text-rose-700">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        @endunless
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        [data-catatan-card] {
            position: relative;
            transform-origin: center;
        }
        [data-catatan-card].is-expanded {
            transform: scale(1.05);
            z-index: 5;
            box-shadow: 0 20px 55px -18px rgba(0, 0, 0, 0.25);
        }
        [data-catatan-card].is-expanded .line-clamp-2,
        [data-catatan-card].is-expanded .line-clamp-3 {
            -webkit-line-clamp: unset;
            line-clamp: unset;
            max-height: none;
            overflow: visible;
            display: block;
            -webkit-box-orient: unset;
            white-space: normal;
        }
        [data-catatan-card] .catatan-full {
            display: none;
        }
        [data-catatan-card].is-expanded .catatan-full {
            display: block;
        }
        [data-catatan-card].is-expanded .catatan-preview {
            display: none;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const cards = document.querySelectorAll('[data-catatan-card]');
            if (!cards.length) return;

            const expand = (card, button) => {
                card.classList.add('is-expanded');
                if (button) {
                    button.setAttribute('aria-pressed', 'true');
                    button.setAttribute('aria-expanded', 'true');
                    button.setAttribute('aria-label', 'Perkecil catatan');
                    button.querySelector('[data-icon="expand"]')?.classList.add('hidden');
                    button.querySelector('[data-icon="collapse"]')?.classList.remove('hidden');
                }
            };

            const collapse = (card, button) => {
                card.classList.remove('is-expanded');
                if (button) {
                    button.setAttribute('aria-pressed', 'false');
                    button.setAttribute('aria-expanded', 'false');
                    button.setAttribute('aria-label', 'Perbesar catatan');
                    button.querySelector('[data-icon="expand"]')?.classList.remove('hidden');
                    button.querySelector('[data-icon="collapse"]')?.classList.add('hidden');
                }
            };

            const toggle = (card, button) => {
                if (card.classList.contains('is-expanded')) {
                    collapse(card, button);
                } else {
                    expand(card, button);
                }
            };

            cards.forEach((card) => {
                const toggleBtn = card.querySelector('[data-catatan-toggle]');
                if (!toggleBtn) return;

                toggleBtn.addEventListener('click', () => toggle(card, toggleBtn));

                const handleCardToggle = (event) => {
                    if (event.target.closest('[data-catatan-toggle], a, button, form')) return;
                    toggle(card, toggleBtn);
                };

                card.addEventListener('click', handleCardToggle);

                card.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        handleCardToggle(event);
                    }
                });

                card.addEventListener('blur', (event) => {
                    // close when focus leaves the card entirely
                    if (!card.contains(event.relatedTarget)) {
                        collapse(card, toggleBtn);
                    }
                }, true);
            });
        })();
    </script>
@endpush
