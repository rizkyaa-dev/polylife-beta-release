@extends('layouts.app')

@section('page_title', 'To-Do List')

@php
    $guestMode = $guestMode ?? false;
    $ongoingTasks = $todolists->where('status', false);
    $completedTasks = $todolists->where('status', true);
    $activeTab = request('tab', 'ongoing');
@endphp

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500">Atur tugas kamu dengan rapi</p>
                <h2 class="text-2xl font-semibold text-gray-900">Pantau Progress Harian</h2>
            </div>
            <a @if($guestMode) aria-disabled="true" @else href="{{ route('todolist.create') }}" @endif
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-sm font-semibold shadow-sm {{ $guestMode ? 'bg-gray-300 cursor-not-allowed' : '' }}"
               style="{{ $guestMode ? '' : 'background-color: #1261DE;' }}">
                {{ $guestMode ? 'Mode baca' : '+ Tugas Baru' }}
            </a>
        </div>

        @if($guestMode)
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-900">
                Mode tamu: centang status dan edit disembunyikan. Ubah contoh data di <code>storage/app/guest/workspace.json</code> jika ingin menyesuaikan.
            </div>
        @endif

        <div class="bg-white border rounded-2xl shadow-sm p-6 space-y-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <p class="text-sm text-gray-500">Total tugas</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $todolists->count() }}</p>
                </div>
                <p class="text-sm text-gray-500 max-w-md">
                    Klik kartu kategori di bawah ini untuk melihat daftar tugas berlangsung atau yang sudah selesai.
                </p>
            </div>

            <div class="space-y-4" id="task-groups">
                <details class="task-group border border-gray-100 rounded-2xl p-4 bg-gray-50" data-group="ongoing" {{ $activeTab === 'ongoing' ? 'open' : '' }}>
                    <summary class="flex items-center justify-between gap-4 cursor-pointer select-none">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-500">Kategori</p>
                            <p class="text-lg font-semibold text-gray-900">Tugas Berlangsung</p>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl text-sm font-semibold bg-white text-indigo-600">
                            <span><span id="ongoing-count">{{ $ongoingTasks->count() }}</span> tugas</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                    </summary>
                    <div class="mt-4 space-y-3">
                        <div id="ongoing-empty" class="{{ $ongoingTasks->isEmpty() ? '' : 'hidden' }}">
                            <div class="text-center py-6 text-gray-500 border border-dashed border-gray-200 rounded-xl">
                                Belum ada tugas berlangsung. Klik <span class="font-semibold">+ Tugas Baru</span> untuk mulai membuat daftar.
                            </div>
                        </div>
                        <ul id="ongoing-list" class="space-y-3 {{ $ongoingTasks->isEmpty() ? 'hidden' : '' }}">
                            @foreach ($ongoingTasks as $task)
                                @include('todolist.partials.task-item', ['task' => $task, 'isCompleted' => false])
                            @endforeach
                        </ul>
                    </div>
                </details>

                <details class="task-group border border-gray-100 rounded-2xl p-4 bg-gray-50" data-group="completed" {{ $activeTab === 'completed' ? 'open' : '' }}>
                    <summary class="flex items-center justify-between gap-4 cursor-pointer select-none">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-500">Kategori</p>
                            <p class="text-lg font-semibold text-gray-900">Tugas Selesai</p>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl text-sm font-semibold bg-white text-emerald-600">
                            <span><span id="completed-count">{{ $completedTasks->count() }}</span> tugas</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                    </summary>
                    <div class="mt-4 space-y-3">
                        <div id="completed-empty" class="{{ $completedTasks->isEmpty() ? '' : 'hidden' }}">
                            <div class="text-center py-6 text-gray-500 border border-dashed border-gray-200 rounded-xl">
                                Belum ada tugas yang ditandai selesai.
                            </div>
                        </div>
                        <ul id="completed-list" class="space-y-3 {{ $completedTasks->isEmpty() ? 'hidden' : '' }}">
                            @foreach ($completedTasks as $task)
                                @include('todolist.partials.task-item', ['task' => $task, 'isCompleted' => true])
                            @endforeach
                        </ul>
                    </div>
                </details>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (document.body.dataset.guestMode === '1') {
                return;
            }

            const badgeBaseClasses = 'task-badge inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium';
            const groups = {
                ongoing: {
                    list: document.getElementById('ongoing-list'),
                    empty: document.getElementById('ongoing-empty'),
                    count: document.getElementById('ongoing-count'),
                    details: document.querySelector('details[data-group="ongoing"]'),
                },
                completed: {
                    list: document.getElementById('completed-list'),
                    empty: document.getElementById('completed-empty'),
                    count: document.getElementById('completed-count'),
                    details: document.querySelector('details[data-group="completed"]'),
                },
            };

            Object.values(groups).forEach(group => {
                if (!group?.details) return;
                const icon = group.details.querySelector('summary svg');
                const setRotation = () => {
                    if (icon) {
                        icon.style.transform = group.details.open ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                };
                group.details.addEventListener('toggle', setRotation);
                setRotation();
            });

            function updateEmptyState(tabName) {
                const group = groups[tabName];
                if (!group || !group.list || !group.empty) return;

                const hasItems = Boolean(group.list.querySelector('.task-item'));
                group.list.classList.toggle('hidden', !hasItems);
                group.empty.classList.toggle('hidden', hasItems);
            }

            function updateCount(tabName) {
                const group = groups[tabName];
                if (!group || !group.count || !group.list) return;
                const total = group.list.querySelectorAll('.task-item').length;
                group.count.textContent = total;
            }

            async function handleToggle(checkbox) {
                const form = checkbox.closest('form');
                if (!form) return;

                const formData = new FormData(form);
                formData.set('status', checkbox.checked ? 1 : 0);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Gagal memperbarui status.');
                    }

                    const payload = await response.json();
                    applyTaskChanges(checkbox, payload);
                } catch (error) {
                    form.submit();
                }
            }

            function applyTaskChanges(checkbox, payload) {
                const taskItem = checkbox.closest('.task-item');
                if (!taskItem) return;

                const hiddenInputId = checkbox.dataset.hiddenInput;
                const hiddenInput = hiddenInputId ? document.getElementById(hiddenInputId) : null;
                if (hiddenInput) {
                    hiddenInput.value = payload.status ? 1 : 0;
                }

                checkbox.checked = payload.status;
                taskItem.dataset.state = payload.tab;
                taskItem.classList.toggle('bg-gray-50', payload.status);
                taskItem.classList.toggle('hover:border-indigo-200', !payload.status);

                const title = taskItem.querySelector('.task-title');
                title?.classList.toggle('line-through', payload.status);

                const timestamp = taskItem.querySelector('.task-timestamp');
                if (timestamp && payload.timestamp) {
                    timestamp.textContent = payload.timestamp;
                }

                const badge = taskItem.querySelector('.task-badge');
                if (badge && payload.badge) {
                    badge.className = `${badgeBaseClasses} ${payload.badge.classes}`;
                    const label = badge.querySelector('.task-badge-label');
                    if (label) {
                        label.textContent = payload.badge.text;
                    }
                }

                const targetGroup = groups[payload.tab];
                const sourceTab = payload.tab === 'completed' ? 'ongoing' : 'completed';

                if (targetGroup?.list) {
                    targetGroup.list.prepend(taskItem);
                }

                updateEmptyState(payload.tab);
                updateEmptyState(sourceTab);
                updateCount(payload.tab);
                updateCount(sourceTab);

                if (targetGroup?.details) {
                    targetGroup.details.setAttribute('open', 'open');
                    const icon = targetGroup.details.querySelector('summary svg');
                    if (icon) {
                        icon.style.transform = 'rotate(180deg)';
                    }
                }
            }

            document.querySelectorAll('.task-status-toggle').forEach((checkbox) => {
                checkbox.addEventListener('change', () => handleToggle(checkbox));
            });
        });
    </script>
@endpush
