        {{-- Kartu: To-Do Prioritas --}}
        <section class="bg-white rounded-2xl shadow-sm border p-4 sm:p-5 h-full flex flex-col min-w-0 dark:bg-slate-900 dark:border-slate-800">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">To-Do List Prioritas</h2>
                <a href="{{ $todolistIndexRoute }}" class="text-sm text-indigo-600 hover:underline">Kelola</a>
            </header>

            @if(!empty($todosPrioritas) && count($todosPrioritas))
                <ul class="space-y-3">
                    @foreach($todosPrioritas as $todo)
                        @php
                            $secondsLeft = $todo->status
                                ? max(0, 600 - now()->diffInSeconds($todo->updated_at ?? now()))
                                : null;
                            $minutesLeft = $secondsLeft ? (int) ceil($secondsLeft / 60) : null;
                        @endphp
                        <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-gray-100 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/40"
                            data-todo-card
                            data-todo-id="{{ $todo->id }}"
                            @if($secondsLeft) data-remove-after="{{ $secondsLeft }}" @endif>
                            <div class="flex items-center gap-3">
                                @if($todolistToggleEnabled)
                                    <input type="checkbox"
                                           data-todo-toggle
                                           data-toggle-url="{{ route('todolist.toggle-status', $todo) }}"
                                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-600 dark:text-indigo-300 dark:focus:ring-indigo-300"
                                           {{ $todo->status ? 'checked' : '' }}>
                                @else
                                    <span class="h-4 w-4 rounded border border-gray-200 bg-gray-100 dark:border-slate-700 dark:bg-slate-800"></span>
                                @endif
                                <span data-todo-text class="text-sm font-medium {{ $todo->status ? 'line-through text-gray-400 dark:text-slate-500' : 'text-gray-800 dark:text-slate-100' }}">
                                    {{ $todo->nama_item }}
                                </span>
                            </div>
                            <div class="flex flex-col gap-1 text-right">
                                @if($todolistEditEnabled)
                                    <a href="{{ route('todolist.edit', $todo->id) }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                                @endif
                                <p class="text-xs text-gray-500 dark:text-slate-400" data-todo-meta>
                                    @if($todo->status)
                                        Ditandai selesai {{ $todo->updated_at?->diffForHumans() }} â€¢ hilang dalam {{ $minutesLeft }} menit
                                    @else
                                        Centang untuk menandai selesai.
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500 dark:text-slate-400">Belum ada to-do prioritas.</p>
            @endif
        </section>

