<?php

namespace Modules\Projects\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Attributes\Url;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Services\TaskDependencyService;

class ProjectGantt extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bars-4';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'projects::filament.pages.gantt';

    #[Url]
    public ?int $project = null;

    public array $ganttData = [];

    public static function getNavigationLabel(): string
    {
        return __('projects::projects.gantt_view');
    }

    public function getTitle(): string
    {
        return __('projects::projects.gantt_view');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('projects.enable_gantt_view', true);
    }

    public function mount(): void
    {
        if (!$this->project) {
            $this->project = Project::active()->notTemplate()->first()?->id;
        }

        $this->loadGanttData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('project')
                    ->label(__('projects::projects.project'))
                    ->options(
                        Project::active()
                            ->notTemplate()
                            ->get()
                            ->mapWithKeys(fn ($p) => [$p->id => "{$p->code} - {$p->display_name}"])
                    )
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadGanttData()),
            ]);
    }

    public function loadGanttData(): void
    {
        if (!$this->project) {
            $this->ganttData = [];
            return;
        }

        $project = Project::find($this->project);
        if (!$project) {
            $this->ganttData = [];
            return;
        }

        $tasks = $project->tasks()
            ->with(['dependencies.dependsOnTask', 'stage', 'milestone'])
            ->orderBy('planned_start_date')
            ->get();

        $dependencyService = app(TaskDependencyService::class);

        $this->ganttData = [
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->display_name,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
            ],
            'tasks' => $tasks->map(function ($task) use ($dependencyService) {
                return [
                    'id' => $task->id,
                    'code' => $task->code,
                    'name' => $task->display_name,
                    'start' => $task->planned_start_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'end' => $task->planned_end_date?->format('Y-m-d') ?? now()->addDays(1)->format('Y-m-d'),
                    'progress' => $task->progress_percent,
                    'stage' => $task->stage->display_name,
                    'stage_color' => $task->stage->color,
                    'is_completed' => $task->is_completed,
                    'milestone' => $task->milestone?->display_name,
                    'dependencies' => $dependencyService->getDependencyChain($task),
                ];
            })->toArray(),
            'milestones' => $project->milestones->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->display_name,
                'date' => $m->target_date?->format('Y-m-d'),
                'is_completed' => $m->is_completed,
            ])->toArray(),
        ];
    }

    public function getProjectProperty(): ?Project
    {
        return $this->project ? Project::find($this->project) : null;
    }

    public static function canAccess(): bool
    {
        return config('projects.enable_gantt_view', true) && (auth()->user()?->can('tasks.view_any') ?? false);
    }
}
