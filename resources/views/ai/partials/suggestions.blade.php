@php
    $suggestions = $suggestions ?? [
        ['icon' => 'calendar', 'label' => 'Atur kegiatan', 'prompt' => 'Apa jadwal kuliah dan tugas yang perlu saya kerjakan minggu ini?'],
        ['icon' => 'wallet', 'label' => 'Cek pengeluaran', 'prompt' => 'Berapa sisa anggaran dan total pengeluaran bulan ini?'],
        ['icon' => 'code', 'label' => 'Bantu coding', 'prompt' => 'Bantu saya merancang solusi coding. Tanyakan dulu tujuan, bahasa pemrograman, dan kebutuhan saya.'],
    ];
@endphp
<div class="ai-suggestions" data-ai-suggestions @if ($hasMessages) hidden @endif aria-label="Ide percakapan">
    @foreach ($suggestions as $suggestion)
        <button type="button" data-ai-prompt="{{ $suggestion['prompt'] }}">
            <x-ai.icon :name="$suggestion['icon']" />
            <span>{{ $suggestion['label'] }}</span>
        </button>
    @endforeach
</div>
