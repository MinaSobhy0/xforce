<?php

namespace XLinic\Framework\Core\Model\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

trait HasActivity
{
    use LogsActivity;

    /**
     * Get the activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => $this->getActivityDescription($eventName))
            ->useLogName($this->getTable());
    }

    /**
     * Get the description for the activity log event.
     */
    protected function getActivityDescription(string $eventName): string
    {
        $modelName = class_basename($this);
        $displayName = $this->getDisplayName();

        return match($eventName) {
            'created' => "{$modelName} '{$displayName}' was created",
            'updated' => "{$modelName} '{$displayName}' was updated",
            'deleted' => "{$modelName} '{$displayName}' was deleted",
            'restored' => "{$modelName} '{$displayName}' was restored",
            default => "{$modelName} '{$displayName}' was {$eventName}",
        };
    }

    /**
     * Get activities for this model.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(
            config('activitylog.activity_model', \Spatie\Activitylog\Models\Activity::class),
            'subject'
        );
    }

    /**
     * Get the latest activity.
     */
    public function latestActivity()
    {
        return $this->activities()->latest()->first();
    }

    /**
     * Check if this model has been updated recently.
     */
    public function wasRecentlyUpdated(int $minutes = 5): bool
    {
        $latestActivity = $this->latestActivity();

        if (!$latestActivity) {
            return false;
        }

        return $latestActivity->created_at->gt(now()->subMinutes($minutes));
    }

    /**
     * Get activities by a specific causer.
     */
    public function activitiesByCauser($causer)
    {
        return $this->activities()->causedBy($causer);
    }

    /**
     * Get activities within a date range.
     */
    public function activitiesInPeriod(\Carbon\Carbon $from, \Carbon\Carbon $to)
    {
        return $this->activities()
            ->whereBetween('created_at', [$from, $to]);
    }
}