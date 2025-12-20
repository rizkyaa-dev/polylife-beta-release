@props([
    'title' => 'Fitur',
    'message' => 'Fitur ini sedang kami siapkan. Nantikan update berikutnya!',
    'backLabel' => 'Kembali ke Dashboard',
    'backRoute' => 'dashboard',
])

<div class="bg-white border rounded-2xl shadow-sm p-10 text-center">
    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-50 text-indigo-600 text-sm font-semibold uppercase tracking-wider">
        <span>Segera Hadir</span>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mt-4">{{ $title }}</h1>
    <p class="text-gray-500 mt-3 leading-relaxed">{{ $message }}</p>

    <div class="mt-8">
        <a href="{{ route($backRoute) }}"
           class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold shadow hover:bg-indigo-700 transition">
            {{ $backLabel }}
        </a>
    </div>
</div>
