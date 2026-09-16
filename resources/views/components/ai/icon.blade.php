@props(['name' => 'spark'])
<svg {{ $attributes->class(['ai-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('workspace')
            <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>
            @break
        @case('chat')
            <path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-6 3V6a2 2 0 0 1 2-2Z"/><path d="M7 9h10M7 13h6"/>
            @break
        @case('settings')
            <path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="var(--ai-surface, white)"/><circle cx="15" cy="17" r="3" fill="var(--ai-surface, white)"/>
            @break
        @case('close')
            <path d="m6 6 12 12M6 18 18 6"/>
            @break
        @case('send')
            <path d="M12 19V5m-6 6 6-6 6 6"/>
            @break
        @case('stop')
            <rect x="7" y="7" width="10" height="10" rx="1" fill="currentColor" stroke="none"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('chevron-down')
            <path d="m7 10 5 5 5-5"/>
            @break
        @case('chevron-left')
            <path d="m15 18-6-6 6-6"/>
            @break
        @case('chevron-right')
            <path d="m9 18 6-6-6-6"/>
            @break
        @case('tool')
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94Z"/>
            @break
        @case('more')
            <circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/>
            @break
        @case('rename')
            <path d="m4 20 4.25-1 10.7-10.7a2.1 2.1 0 0 0-3-3L5.25 16 4 20Z"/><path d="m14.5 6.75 3 3"/>
            @break
        @case('trash')
            <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4m8-4v4M3 11h18m-13 5h4"/>
            @break
        @case('wallet')
            <rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18m0 4h-6v4h6"/>
            @break
        @case('todo')
            <path d="m3 6 2 2 3-4m3 2h10M3 13h4m4 0h10M3 20h4m4 0h10"/>
            @break
        @default
            <path d="M12 2c1.1 5.7 4.3 8.9 10 10-5.7 1.1-8.9 4.3-10 10C10.9 16.3 7.7 13.1 2 12c5.7-1.1 8.9-4.3 10-10Z"/>
    @endswitch
</svg>
