        {{-- Kartu: Reminder Mendatang --}}
        <section class="bg-white rounded-2xl shadow-sm border p-4 sm:p-5 h-full flex flex-col min-w-0 dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Reminder Mendatang</h2>
                <div class="flex items-center gap-3">
                    @unless($guestMode)
                        <button type="button" class="hidden text-sm text-indigo-600 hover:underline" data-reminder-notification-toggle>
                            Aktifkan notifikasi
                        </button>
                    @endunless
                    @if($reminderManageRoute)
                        <a href="{{ $reminderManageRoute }}" class="text-sm text-indigo-600 hover:underline">Kelola</a>
                    @endif
                </div>
            </header>

            @php
                $hasReminders = isset($remindersMendatang) && count($remindersMendatang);
            @endphp
            @php
                $hasReminders = isset($remindersMendatang) && count($remindersMendatang);
            @endphp
            <ul class="divide-y divide-gray-100 dark:divide-slate-800" data-reminder-list>
                @forelse($remindersMendatang as $r)
                    <li class="py-3 space-y-2"
                        data-reminder-item
                        data-reminder-id="{{ $r['id'] }}"
                        data-seconds-left="{{ $r['seconds_left'] }}"
                        data-reminder-title="{{ $r['title'] }}"
                        data-reminder-deadline="{{ $r['waktu_formatted'] }}">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-gray-800">{{ $r['title'] }}</p>
                                <p class="text-sm text-gray-500">Tenggat: {{ $r['waktu_formatted'] }}</p>
                            </div>
                            <a href="{{ $r['edit_url'] }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-2 rounded-lg px-3 py-1 text-xs font-semibold border {{ $r['badge_classes'] }} {{ !empty($r['blink']) ? 'reminder-blink' : '' }}"
                                  data-reminder-badge>
                                <span class="reminder-dot h-2.5 w-2.5 rounded-full {{ $r['dot_classes'] }}"
                                      data-reminder-dot></span>
                                <span data-reminder-timeleft>{{ $r['time_left_text'] }}</span>
                            </span>
                        </div>
                    </li>
                @empty
                @endforelse
            </ul>
            <p class="text-sm text-gray-500 dark:text-slate-400 {{ $hasReminders ? 'hidden' : '' }}" id="reminderEmptyState">
                Belum ada reminder aktif.
            </p>
        </section>
