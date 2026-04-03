<?php

namespace Modules\Projects\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectStage;
use Modules\Projects\Services\ProjectService;

class ProjectKanban extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'projects::filament.pages.kanban';

    #[Url]
    public ?int $project = null;

    public array $kanbanData = [];

    public static function getNavigationLabel(): string
    {
        return __('projects::projects.kanban_board');
    }

    public function getTitle(): string
    {
        return __('projects::projects.kanban_board');
    }

    public function mount(): void
    {
        // If no project selected, get the first active project
        if (!$this->project) {
            $this->project = Project::active()->notTemplate()->first()?->id;
        }

        $this->loadKanbanData();
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
                    ->afterStateUpdated(fn () => $this->loadKanbanData()),
            ]);
    }

    public function loadKanbanData(): void
    {
        if (!$this->project) {
            $this->kanbanData = [];
            return;
        }

        $project = Project::find($this->project);
        if (!$project) {
            $this->kanbanData = [];
            return;
        }

        $service = app(ProjectService::class);
        $this->kanbanData = $service->getKanbanData($project);
    }

    #[On('task-moved')]
    public function moveTask(int $taskId, int $stageId, int $sortOrder): void
    {
        $task = ProjectTask::find($taskId);

        if (!$task || $task->project_id !== $this->project) {
            return;
        }

        $task->moveToStage($stageId, $sortOrder);
        $this->loadKanbanData();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('projects::tasks.messages.task_moved'),
        ]);
    }

    #[On('quick-create-task')]
    public function quickCreateTask(int $stageId, string $name): void
    {
        if (!$this->project || empty($name)) {
            return;
        }

        $project = Project::find($this->project);

        ProjectTask::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $this->project,
            'stage_id' => $stageId,
            'name' => ['en' => $name],
            'priority' => ProjectTask::PRIORITY_MEDIUM,
            'created_by_id' => auth()->id(),
        ]);

        $this->loadKanbanData();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('projects::tasks.messages.task_created'),
        ]);
    }

    public function getProjectProperty(): ?Project
    {
        return $this->project ? Project::find($this->project) : null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('tasks.view_any') ?? false;
    }
}
