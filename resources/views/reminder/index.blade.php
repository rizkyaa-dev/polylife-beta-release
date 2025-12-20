@extends('layouts.app')

@section('page_title', 'Reminder')

@section('content')
    <div class="space-y-6">
        <div class="bg-white border rounded-3xl shadow-sm p-6 sm:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold">Reminder cerdas</p>
                    <h2 class="text-2xl font-bold text-gray-900">Kelola Reminder</h2>
                    <p class="text-sm text-gray-500 mt-1">Aktifkan pengingat untuk to-do, tugas, jadwal, atau kegiatan supaya tenggatmu aman.</p>
                </div>
                <a href="{{ route('reminder.create') }}"
                   class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                    + Reminder Baru
                </a>
            </div>
        </div>

        @if($reminders->isEmpty())
            <div class="bg-white border rounded-3xl shadow-sm p-8 text-center space-y-3">
                <p class="text-lg font-semibold text-gray-900">Belum ada reminder</p>
                <p class="text-sm text-gray-500">Tambahkan reminder pertama untuk memastikan semua aktivitas penting diingatkan tepat waktu.</p>
                <a href="{{ route('reminder.create') }}"
                   class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                    Buat Reminder
                </a>
            </div>
        @else
            <div class="grid gap-4">
                @foreach($reminders as $reminder)
                    @php
                        $type = 'todolist';
                        $targetLabel = 'To-Do';
                        $targetName = optional($reminder->todolist)->nama_item ?? '-';
                        $typeColor = 'bg-indigo-50 text-indigo-700';
                        $typeBorder = 'border-indigo-200';
                        $accentDot = 'bg-indigo-500';

                        if ($reminder->tugas_id) {
                            $type = 'tugas';
                            $targetLabel = 'Tugas Kuliah';
                            $targetName = optional($reminder->tugas)->nama_tugas ?? '-';
                            $typeColor = 'bg-amber-50 text-amber-700';
                            $typeBorder = 'border-amber-200';
                            $accentDot = 'bg-amber-500';
                        } elseif ($reminder->jadwal_id) {
                            $type = 'jadwal';
                            $targetLabel = 'Agenda Kuliah';
                            $targetName = $reminder->jadwal->catatan_tambahan ?: ucfirst($reminder->jadwal->jenis ?? 'Agenda');
                            $typeColor = 'bg-emerald-50 text-emerald-700';
                            $typeBorder = 'border-emerald-200';
                            $accentDot = 'bg-emerald-500';
                        } elseif ($reminder->kegiatan_id) {
                            $type = 'kegiatan';
                            $targetLabel = 'Kegiatan Detail';
                            $targetName = optional($reminder->kegiatan)->nama_kegiatan ?? '-';
                            $typeColor = 'bg-rose-50 text-rose-700';
                            $typeBorder = 'border-rose-200';
                            $accentDot = 'bg-rose-500';
                        }

                        $timeLabel = optional($reminder->waktu_reminder)->translatedFormat('l, d F Y • H:i');
                        $statusLabel = $reminder->aktif ? 'Aktif' : 'Nonaktif';
                        $statusClass = $reminder->aktif ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500';
                    @endphp
                    <div class="bg-white border rounded-3xl shadow-sm p-4 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold {{ $typeColor }} {{ $typeBorder }}">
                                        {{ $targetLabel }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                <div class="flex items-start gap-2 text-gray-900">
                                    <span class="mt-1 h-2 w-2 rounded-full {{ $accentDot }}"></span>
                                    <div>
                                        <p class="text-base font-semibold">{{ $targetName }}</p>
                                        <p class="text-sm text-gray-500">{{ $timeLabel }}</p>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 space-y-1">
                                    @if($reminder->todolist_id && $reminder->todolist)
                                        <p>To-Do: {{ $reminder->todolist->nama_item }}</p>
                                    @endif
                                    @if($reminder->tugas_id && $reminder->tugas)
                                        <p>Tugas: {{ $reminder->tugas->nama_tugas }} • Deadline {{ optional($reminder->tugas->deadline)->translatedFormat('d M Y') }}</p>
                                    @endif
                                    @if($reminder->jadwal_id && $reminder->jadwal)
                                        <p>Jadwal: {{ $reminder->jadwal->catatan_tambahan ?: 'Agenda' }} • {{ optional($reminder->jadwal->tanggal_mulai)->translatedFormat('d M Y') }}</p>
                                    @endif
                                    @if($reminder->kegiatan_id && $reminder->kegiatan)
                                        <p>Kegiatan: {{ $reminder->kegiatan->nama_kegiatan }} • {{ optional($reminder->kegiatan->tanggal_deadline)->translatedFormat('d M Y') }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-2 text-sm">
                                <a href="{{ route('reminder.edit', $reminder) }}"
                                   class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-1.5 font-semibold text-gray-600 hover:border-indigo-200 hover:text-indigo-600">
                                    Edit
                                </a>
                                <form action="{{ route('reminder.destroy', $reminder) }}" method="POST"
                                      onsubmit="return confirm('Hapus reminder ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center rounded-xl border border-rose-200 px-3 py-1.5 font-semibold text-rose-600 hover:bg-rose-50">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
