<?php

namespace Modules\Projects\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectTag extends BaseModel
{
    protected $table = 'project_tags';

    // Disable auto branch assignment for tags
    protected bool $autoSetBranchId = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTask::class, 'project_task_tag', 'tag_id', 'task_id')
            ->withTimestamps();
    }

    // Computed attributes
    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->name[$locale] ?? $this->name['en'] ?? '';
    }

    public function getTaskCountAttribute(): int
    {
        return $this->tasks()->count();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->whereRaw("name->>'en' ILIKE ?", ["%{$term}%"])
                ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$term}%"]);
        });
    }
}
