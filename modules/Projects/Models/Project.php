<?php

namespace Modules\Projects\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;

class Project extends BaseModel
{
    use HasActivity, HasSequence, SoftDeletes;

    protected $table = 'projects';

    // Configure sequence
    protected string $sequenceCode = 'project';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'branch_id',
        'manager_id',
        'customer_id',
        'status',
        'priority',
        'start_date',
        'end_date',
        'deadline',
        'budget_minor',
        'actual_cost_minor',
        'color',
        'settings',
        'allow_timesheets',
        'is_template',
        'is_active',
        'odoo_id',
        'odoo_synced_at',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'settings' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'deadline' => 'date',
        'budget_minor' => 'integer',
        'actual_cost_minor' => 'integer',
        'allow_timesheets' => 'boolean',
        'is_template' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Status constants
    public const STATUS_PLANNING = 'planning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PLANNING => 'Planning',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_ON_HOLD => 'On Hold',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    // Priority constants
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_MEDIUM => 'Medium',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Project $project) {
            if (empty($project->status)) {
                $project->status = self::STATUS_PLANNING;
            }
            if (empty($project->priority)) {
                $project->priority = self::PRIORITY_MEDIUM;
            }
        });

        // Create default stages when project is created
        static::created(function (Project $project) {
            if (!$project->is_template) {
                $project->createDefaultStages();
            }
        });
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTimeEntry::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Computed attributes
    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->name[$locale] ?? $this->name['en'] ?? '';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function getProgressPercentAttribute(): int
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->tasks()
            ->whereHas('stage', fn ($q) => $q->where('is_final', true))
            ->count();

        return (int) round(($completedTasks / $totalTasks) * 100);
    }

    public function getTotalEstimatedHoursAttribute(): int
    {
        return $this->tasks()->sum('estimated_hours') ?? 0;
    }

    public function getTotalActualHoursAttribute(): float
    {
        return $this->timeEntries()->sum('hours') ?? 0;
    }

    // State machine
    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_PLANNING => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_ACTIVE => [self::STATUS_ON_HOLD, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_ON_HOLD => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [self::STATUS_ACTIVE],
            self::STATUS_CANCELLED => [self::STATUS_PLANNING],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    // State checks
    public function isPlanning(): bool
    {
        return $this->status === self::STATUS_PLANNING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOnHold(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // Create default stages
    public function createDefaultStages(): void
    {
        $defaultStages = config('projects.default_stages', [
            ['name' => ['en' => 'Backlog', 'ar' => 'قائمة الانتظار'], 'status_type' => 'todo', 'color' => '#6B7280', 'fold_by_default' => true],
            ['name' => ['en' => 'To Do', 'ar' => 'للتنفيذ'], 'status_type' => 'todo', 'color' => '#3B82F6', 'fold_by_default' => false],
            ['name' => ['en' => 'In Progress', 'ar' => 'قيد التنفيذ'], 'status_type' => 'in_progress', 'color' => '#F59E0B', 'fold_by_default' => false],
            ['name' => ['en' => 'Review', 'ar' => 'للمراجعة'], 'status_type' => 'review', 'color' => '#8B5CF6', 'fold_by_default' => false],
            ['name' => ['en' => 'Done', 'ar' => 'مكتمل'], 'status_type' => 'done', 'color' => '#10B981', 'fold_by_default' => false, 'is_final' => true],
        ]);

        foreach ($defaultStages as $index => $stageData) {
            $this->stages()->create([
                'tenant_id' => $this->tenant_id,
                'name' => $stageData['name'],
                'status_type' => $stageData['status_type'],
                'color' => $stageData['color'],
                'fold_by_default' => $stageData['fold_by_default'] ?? false,
                'is_final' => $stageData['is_final'] ?? false,
                'sort_order' => $index,
            ]);
        }
    }

    // Clone as template
    public function cloneAsProject(string $name): Project
    {
        $newProject = $this->replicate(['code']);
        $newProject->name = $name;
        $newProject->is_template = false;
        $newProject->status = self::STATUS_PLANNING;
        $newProject->start_date = null;
        $newProject->end_date = null;
        $newProject->actual_cost_minor = 0;
        $newProject->save();

        // Clone stages
        $stageMap = [];
        foreach ($this->stages as $stage) {
            $newStage = $stage->replicate();
            $newStage->project_id = $newProject->id;
            $newStage->save();
            $stageMap[$stage->id] = $newStage->id;
        }

        // Clone milestones
        foreach ($this->milestones as $milestone) {
            $newMilestone = $milestone->replicate();
            $newMilestone->project_id = $newProject->id;
            $newMilestone->completed_date = null;
            $newMilestone->status = ProjectMilestone::STATUS_PENDING;
            $newMilestone->save();
        }

        return $newProject;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotTemplate($query)
    {
        return $query->where('is_template', false);
    }

    public function scopeTemplate($query)
    {
        return $query->where('is_template', true);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereRaw("name->>'en' ILIKE ?", ["%{$term}%"])
                ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$term}%"]);
        });
    }
}
