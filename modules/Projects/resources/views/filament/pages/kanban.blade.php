<x-filament-panels::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    @if($this->project && count($kanbanData) > 0)
        <div
            class="kanban-board overflow-x-auto"
            x-data="kanbanBoard()"
            x-init="initSortable()"
        >
            <div class="flex gap-4 min-w-max pb-4">
                @foreach($kanbanData as $stage)
                    <div
                        class="kanban-column bg-gray-100 dark:bg-gray-800 rounded-lg p-3 w-80 flex-shrink-0"
                        data-stage-id="{{ $stage['id'] }}"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-3 h-3 rounded-full"
                                    style="background-color: {{ $stage['color'] }}"
                                ></div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">
                                    {{ $stage['name'] }}
                                </h3>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    ({{ $stage['task_count'] }})
                                </span>
                            </div>
                        </div>

                        <div
                            class="kanban-tasks space-y-2 min-h-[200px]"
                            data-stage-id="{{ $stage['id'] }}"
                        >
                            @foreach($stage['tasks'] as $task)
                                <div
                                    class="kanban-task bg-white dark:bg-gray-700 rounded-lg p-3 shadow-sm border border-gray-200 dark:border-gray-600 cursor-move"
                                    data-task-id="{{ $task['id'] }}"
                                >
                                    <div class="flex items-start justify-between mb-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                            {{ $task['code'] }}
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @if($task['priority'] === 'urgent') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                            @elseif($task['priority'] === 'high') bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                            @elseif($task['priority'] === 'medium') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200
                                            @endif
                                        ">
                                            {{ ucfirst($task['priority']) }}
                                        </span>
                                    </div>

                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                                        {{ $task['name'] }}
                                    </h4>

                                    @if(count($task['tags']) > 0)
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            @foreach($task['tags'] as $tag)
                                                <span
                                                    class="px-2 py-0.5 rounded-full text-xs text-white"
                                                    style="background-color: {{ $tag['color'] }}"
                                                >
                                                    {{ $tag['name'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100 dark:border-gray-600">
                                        @if($task['assignee'])
                                            <div class="flex items-center gap-1">
                                                <div class="w-6 h-6 rounded-full bg-primary-500 flex items-center justify-center text-white text-xs">
                                                    {{ substr($task['assignee']['name'], 0, 1) }}
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $task['assignee']['name'] }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">{{ __('Unassigned') }}</span>
                                        @endif

                                        @if($task['deadline'])
                                            <span class="text-xs {{ $task['is_overdue'] ? 'text-red-500' : 'text-gray-500 dark:text-gray-400' }}">
                                                <x-heroicon-o-calendar class="w-3 h-3 inline" />
                                                {{ $task['deadline'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($task['subtask_count'] > 0)
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            <x-heroicon-o-list-bullet class="w-3 h-3 inline" />
                                            {{ $task['completed_subtask_count'] }}/{{ $task['subtask_count'] }} subtasks
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Quick Add Task --}}
                        <div class="mt-2" x-data="{ adding: false, taskName: '' }">
                            <button
                                x-show="!adding"
                                @click="adding = true; $nextTick(() => $refs.taskInput.focus())"
                                class="w-full py-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center justify-center gap-1"
                            >
                                <x-heroicon-o-plus class="w-4 h-4" />
                                {{ __('Add Task') }}
                            </button>

                            <div x-show="adding" class="space-y-2">
                                <input
                                    x-ref="taskInput"
                                    x-model="taskName"
                                    @keydown.enter="if(taskName) { $wire.quickCreateTask({{ $stage['id'] }}, taskName); taskName = ''; adding = false; }"
                                    @keydown.escape="adding = false; taskName = ''"
                                    type="text"
                                    placeholder="{{ __('Task name...') }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                                />
                                <div class="flex gap-2">
                                    <button
                                        @click="if(taskName) { $wire.quickCreateTask({{ $stage['id'] }}, taskName); taskName = ''; adding = false; }"
                                        class="flex-1 py-1 text-sm bg-primary-500 text-white rounded hover:bg-primary-600"
                                    >
                                        {{ __('Add') }}
                                    </button>
                                    <button
                                        @click="adding = false; taskName = ''"
                                        class="py-1 px-3 text-sm text-gray-500 hover:text-gray-700"
                                    >
                                        {{ __('Cancel') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="text-center py-12 text-gray-500">
            @if(!$this->project)
                {{ __('projects::projects.messages.select_project') }}
            @else
                {{ __('projects::projects.messages.no_tasks') }}
            @endif
        </div>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        function kanbanBoard() {
            return {
                initSortable() {
                    document.querySelectorAll('.kanban-tasks').forEach(column => {
                        new Sortable(column, {
                            group: 'kanban',
                            animation: 150,
                            ghostClass: 'opacity-50',
                            dragClass: 'shadow-lg',
                            onEnd: (evt) => {
                                const taskId = parseInt(evt.item.dataset.taskId);
                                const stageId = parseInt(evt.to.dataset.stageId);
                                const sortOrder = evt.newIndex;

                                @this.moveTask(taskId, stageId, sortOrder);
                            }
                        });
                    });
                }
            }
        }
    </script>
    @endpush
</x-filament-panels::page>
