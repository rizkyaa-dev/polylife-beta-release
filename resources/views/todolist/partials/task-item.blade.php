@php
    $guestMode = $guestMode ?? false;
    $hasReminderRecord = $task->reminders->isNotEmpty();
    $hasActiveReminder = $task->reminders->where('aktif', true)->isNotEmpty();

    if (! $hasReminderRecord) {
        $reminderBadge = ['text' => 'Tanpa reminder', 'classes' => 'bg-gray-100 text-gray-600'];
    } elseif ($hasActiveReminder) {
        $reminderBadge = ['text' => 'Reminder aktif', 'classes' => 'bg-indigo-50 text-indigo-700'];
    } else {
        $reminderBadge = ['text' => 'Reminder nonaktif', 'classes' => 'bg-amber-50 text-amber-700'];
    }
@endphp

<li @class([
        'task-item flex items-center justify-between gap-3 p-4 border border-gray-100 rounded-xl transition',
        'bg-gray-50' => $isCompleted,
        'hover:border-indigo-200' => ! $isCompleted,
    ])
    data-task-id="{{ $task->id }}"
    data-state="{{ $isCompleted ? 'completed' : 'ongoing' }}">
    <div class="flex items-start gap-3">
        @if($guestMode)
            <span class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded border border-gray-200 bg-gray-100 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </span>
        @else
            <form action="{{ route('todolist.toggle-status', $task) }}" method="POST" class="mt-1 task-toggle-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" id="status-input-{{ $task->id }}" value="{{ $task->status ? 1 : 0 }}">
                <input type="checkbox"
                       class="task-status-toggle h-5 w-5 rounded border-gray-300 bg-white text-indigo-600 focus:ring-indigo-500 transition-colors duration-200 dark:border-slate-600 dark:bg-slate-800 dark:text-indigo-400 dark:checked:border-indigo-400 dark:checked:bg-indigo-500 dark:focus:ring-indigo-400"
                       data-hidden-input="status-input-{{ $task->id }}"
                       {{ $task->status ? 'checked' : '' }}>
            </form>
        @endif
        <div>
            <p class="task-title font-semibold text-gray-900 {{ $isCompleted ? 'line-through' : '' }}">{{ $task->nama_item }}</p>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <span class="task-badge inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium {{ $reminderBadge['classes'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="task-badge-label">{{ $reminderBadge['text'] }}</span>
                </span>
                <span class="task-timestamp">
                    {{ $isCompleted ? 'Selesai ' . ($task->updated_at?->diffForHumans() ?? '-') : 'Dibuat ' . ($task->created_at?->diffForHumans() ?? '-') }}
                </span>
            </div>
        </div>
    </div>
    @if($guestMode)
        <span class="inline-flex items-center gap-1 text-sm font-semibold text-gray-400 px-3 py-1.5" aria-disabled="true">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036l-9.193 9.193a2 2 0 00-.512.878l-.708 2.829 2.829-.707a2 2 0 00.878-.512l9.193-9.193a1.5 1.5 0 000-2.121z" />
            </svg>
            Edit nonaktif
        </span>
    @else
        <a href="{{ route('todolist.edit', $task) }}"
           class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-200 rounded-lg px-3 py-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036l-9.193 9.193a2 2 0 00-.512.878l-.708 2.829 2.829-.707a2 2 0 00.878-.512l9.193-9.193a1.5 1.5 0 000-2.121z" />
            </svg>
            Edit
        </a>
    @endif
</li>
