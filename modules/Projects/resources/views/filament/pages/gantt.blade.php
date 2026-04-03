<x-filament-panels::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    @if($this->project && count($ganttData['tasks'] ?? []) > 0)
        <div
            id="gantt-chart"
            class="bg-white dark:bg-gray-800 rounded-lg shadow p-4"
            x-data="ganttChart()"
            x-init="initGantt()"
        >
            {{-- Gantt chart will be rendered here by JavaScript --}}
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

    @push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.css">
    <style>
        .gantt .bar-label {
            font-size: 12px;
        }
        .gantt .bar {
            cursor: pointer;
        }
        .gantt .bar-progress {
            fill: #10b981;
        }
        .dark .gantt-container {
            background: #1f2937;
        }
        .dark .gantt .grid-row {
            fill: #374151;
        }
        .dark .gantt .row-line {
            stroke: #4b5563;
        }
        .dark .gantt .tick {
            stroke: #4b5563;
        }
        .dark .gantt .lower-text, .dark .gantt .upper-text {
            fill: #9ca3af;
        }
        .dark .gantt .bar-label {
            fill: #fff;
        }
    </style>
    @endpush

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
    <script>
        function ganttChart() {
            return {
                gantt: null,
                initGantt() {
                    const ganttData = @json($ganttData);

                    if (!ganttData.tasks || ganttData.tasks.length === 0) {
                        return;
                    }

                    const tasks = ganttData.tasks.map(task => ({
                        id: task.id.toString(),
                        name: `${task.code}: ${task.name}`,
                        start: task.start,
                        end: task.end,
                        progress: task.progress,
                        dependencies: task.dependencies
                            .filter(d => d.dependency_type === 'finish_to_start')
                            .map(d => d.from_task_id.toString())
                            .join(', '),
                        custom_class: task.is_completed ? 'bar-completed' : ''
                    }));

                    this.gantt = new Gantt('#gantt-chart', tasks, {
                        view_modes: ['Quarter Day', 'Half Day', 'Day', 'Week', 'Month'],
                        view_mode: 'Week',
                        date_format: 'YYYY-MM-DD',
                        popup_trigger: 'click',
                        custom_popup_html: function(task) {
                            const originalTask = ganttData.tasks.find(t => t.id.toString() === task.id);
                            return `
                                <div class="p-3 min-w-[200px]">
                                    <h5 class="font-semibold mb-1">${task.name}</h5>
                                    <p class="text-sm text-gray-500">
                                        ${originalTask?.stage || ''} | ${originalTask?.progress || 0}% complete
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        ${task._start.format('MMM D')} - ${task._end.format('MMM D, YYYY')}
                                    </p>
                                    ${originalTask?.milestone ? `<p class="text-xs text-purple-500 mt-1">Milestone: ${originalTask.milestone}</p>` : ''}
                                </div>
                            `;
                        },
                        on_click: function(task) {
                            window.location.href = '/admin/project-tasks/' + task.id;
                        },
                        on_progress_change: function(task, progress) {
                            // Could emit Livewire event to save progress
                            console.log('Progress changed:', task.id, progress);
                        },
                        on_date_change: function(task, start, end) {
                            // Could emit Livewire event to save date changes
                            console.log('Date changed:', task.id, start, end);
                        }
                    });

                    // Add milestones as vertical lines
                    if (ganttData.milestones && ganttData.milestones.length > 0) {
                        ganttData.milestones.forEach(milestone => {
                            if (milestone.date) {
                                // Add milestone marker (implementation depends on chart library)
                            }
                        });
                    }
                }
            }
        }
    </script>
    @endpush
</x-filament-panels::page>
