@props([
    'action',
    'method' => 'POST',
    'kegiatan' => null,
    'jadwals' => collect(),
    'submitLabel' => 'Simpan',
])

@php
    $defaultDeadline = old(
        'tanggal_deadline',
        optional(optional($kegiatan)->tanggal_deadline ? \Illuminate\Support\Carbon::parse($kegiatan->tanggal_deadline) : now())->format('Y-m-d')
    );

    $existingTime = null;
    if (!empty($kegiatan?->waktu)) {
        try {
            $existingTime = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $kegiatan->waktu)->format('H:i');
        } catch (\Exception $e) {
            $existingTime = $kegiatan->waktu;
        }
    }
    $defaultTime = old('waktu', $existingTime ?? now()->format('H:i'));
@endphp

@php
    $labelClass = 'form-label';
    $inputClass = 'form-input';
@endphp

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if(!in_array(strtoupper($method), ['GET', 'POST']))
        @method($method)
    @endif

    <div>
        <label for="jadwal_id" class="{{ $labelClass }}">Terhubung ke Jadwal</label>
        <select id="jadwal_id"
                name="jadwal_id"
                class="mt-2 {{ $inputClass }}"
                required>
            <option value="">Pilih jadwal utama</option>
            @foreach($jadwals as $jadwal)
                <option value="{{ $jadwal->id }}"
                    {{ old('jadwal_id', $kegiatan->jadwal_id ?? request('jadwal_id')) == $jadwal->id ? 'selected' : '' }}>
                    {{ $jadwal->catatan_tambahan ?: ucfirst($jadwal->jenis ?? 'Agenda') }}
                    | {{ \Illuminate\Support\Carbon::parse($jadwal->tanggal_mulai)->translatedFormat('d M Y') }}
                </option>
            @endforeach
        </select>
        @error('jadwal_id')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="nama_kegiatan" class="{{ $labelClass }}">Nama Kegiatan</label>
        <input type="text"
               id="nama_kegiatan"
               name="nama_kegiatan"
               value="{{ old('nama_kegiatan', $kegiatan->nama_kegiatan ?? '') }}"
               class="mt-2 {{ $inputClass }}"
               placeholder="Contoh: Presentasi bab 3"
               required>
        @error('nama_kegiatan')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-1">
            <label for="tanggal_deadline" class="{{ $labelClass }}">Tanggal</label>
            <input type="date"
                   id="tanggal_deadline"
                   name="tanggal_deadline"
                   value="{{ $defaultDeadline }}"
                   class="mt-2 {{ $inputClass }}"
                   required>
            @error('tanggal_deadline')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="waktu" class="{{ $labelClass }}">Jam</label>
            <input type="time"
                   id="waktu"
                   name="waktu"
                   value="{{ $defaultTime }}"
                   class="mt-2 {{ $inputClass }}"
                   required>
            @error('waktu')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="lokasi" class="{{ $labelClass }}">Lokasi / Ruangan</label>
            <input type="text"
                   id="lokasi"
                   name="lokasi"
                   value="{{ old('lokasi', $kegiatan->lokasi ?? '') }}"
                   class="mt-2 {{ $inputClass }}"
                   placeholder="Opsional">
            @error('lokasi')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="status" class="{{ $labelClass }}">Status</label>
        <select id="status"
                name="status"
                class="mt-2 {{ $inputClass }}">
            @foreach(['belum_dimulai' => 'Belum dimulai', 'berlangsung' => 'Berlangsung', 'selesai' => 'Selesai'] as $value => $label)
                <option value="{{ $value }}" {{ old('status', $kegiatan->status ?? 'belum_dimulai') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('status')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('kegiatan.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200">← Kembali ke daftar kegiatan</a>
        <button type="submit"
                class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 dark:bg-indigo-500 dark:hover:bg-indigo-400">
            {{ $submitLabel }}
        </button>
    </div>
</form>
