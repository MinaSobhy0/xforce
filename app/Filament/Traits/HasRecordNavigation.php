<?php

namespace App\Filament\Traits;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

trait HasRecordNavigation
{
    /**
     * Cache for adjacent records to avoid multiple queries.
     */
    protected ?array $adjacentRecordsCache = null;

    /**
     * Get adjacent (previous and next) records.
     */
    protected function getAdjacentRecords(): array
    {
        if ($this->adjacentRecordsCache !== null) {
            return $this->adjacentRecordsCache;
        }

        $resource = static::getResource();
        $model = $resource::getModel();
        $currentRecord = $this->getRecord();

        // Get the table's default sort column and direction
        $sortColumn = $this->getNavigationSortColumn();
        $sortDirection = $this->getNavigationSortDirection();

        // Build base query with same scopes as the resource
        $query = $model::query();

        // Apply resource's eloquent query modifications if available
        if (method_exists($resource, 'getEloquentQuery')) {
            $query = $resource::getEloquentQuery();
        }

        // Get the current record's value for the sort column
        $currentValue = $currentRecord->{$sortColumn};
        $currentId = $currentRecord->getKey();

        // Find previous record (the one that comes before in the list)
        $previousRecord = null;
        $nextRecord = null;

        if ($sortDirection === 'desc') {
            // Descending: previous has higher value, next has lower value
            $previousRecord = (clone $query)
                ->where(function ($q) use ($sortColumn, $currentValue, $currentId) {
                    $q->where($sortColumn, '>', $currentValue)
                        ->orWhere(function ($q2) use ($sortColumn, $currentValue, $currentId) {
                            $q2->where($sortColumn, '=', $currentValue)
                                ->where((new ($this->getRecord()::class))->getKeyName(), '>', $currentId);
                        });
                })
                ->orderBy($sortColumn, 'asc')
                ->orderBy((new ($this->getRecord()::class))->getKeyName(), 'asc')
                ->first();

            $nextRecord = (clone $query)
                ->where(function ($q) use ($sortColumn, $currentValue, $currentId) {
                    $q->where($sortColumn, '<', $currentValue)
                        ->orWhere(function ($q2) use ($sortColumn, $currentValue, $currentId) {
                            $q2->where($sortColumn, '=', $currentValue)
                                ->where((new ($this->getRecord()::class))->getKeyName(), '<', $currentId);
                        });
                })
                ->orderBy($sortColumn, 'desc')
                ->orderBy((new ($this->getRecord()::class))->getKeyName(), 'desc')
                ->first();
        } else {
            // Ascending: previous has lower value, next has higher value
            $previousRecord = (clone $query)
                ->where(function ($q) use ($sortColumn, $currentValue, $currentId) {
                    $q->where($sortColumn, '<', $currentValue)
                        ->orWhere(function ($q2) use ($sortColumn, $currentValue, $currentId) {
                            $q2->where($sortColumn, '=', $currentValue)
                                ->where((new ($this->getRecord()::class))->getKeyName(), '<', $currentId);
                        });
                })
                ->orderBy($sortColumn, 'desc')
                ->orderBy((new ($this->getRecord()::class))->getKeyName(), 'desc')
                ->first();

            $nextRecord = (clone $query)
                ->where(function ($q) use ($sortColumn, $currentValue, $currentId) {
                    $q->where($sortColumn, '>', $currentValue)
                        ->orWhere(function ($q2) use ($sortColumn, $currentValue, $currentId) {
                            $q2->where($sortColumn, '=', $currentValue)
                                ->where((new ($this->getRecord()::class))->getKeyName(), '>', $currentId);
                        });
                })
                ->orderBy($sortColumn, 'asc')
                ->orderBy((new ($this->getRecord()::class))->getKeyName(), 'asc')
                ->first();
        }

        $this->adjacentRecordsCache = [
            'previous' => $previousRecord,
            'next' => $nextRecord,
        ];

        return $this->adjacentRecordsCache;
    }

    /**
     * Get the column to use for navigation sorting.
     * Override this method to use a different column.
     */
    protected function getNavigationSortColumn(): string
    {
        return 'created_at';
    }

    /**
     * Get the sort direction for navigation.
     * Override this method to change the direction.
     */
    protected function getNavigationSortDirection(): string
    {
        return 'desc';
    }

    /**
     * Get the previous record.
     */
    protected function getPreviousRecord(): ?Model
    {
        return $this->getAdjacentRecords()['previous'];
    }

    /**
     * Get the next record.
     */
    protected function getNextRecord(): ?Model
    {
        return $this->getAdjacentRecords()['next'];
    }

    /**
     * Get the URL for a record.
     */
    protected function getRecordNavigationUrl(Model $record): string
    {
        $resource = static::getResource();

        // Determine if we're on a view or edit page
        $pageType = str_contains(static::class, 'View') ? 'view' : 'edit';

        return $resource::getUrl($pageType, ['record' => $record]);
    }

    /**
     * Get navigation actions for the header.
     */
    protected function getRecordNavigationActions(): array
    {
        $isRtl = app()->getLocale() === 'ar';
        $previous = $this->getPreviousRecord();
        $next = $this->getNextRecord();

        $actions = [];

        // Previous action (left arrow in LTR, right arrow in RTL)
        $actions[] = Action::make('previousRecord')
            ->icon($isRtl ? 'heroicon-o-chevron-right' : 'heroicon-o-chevron-left')
            ->color('gray')
            ->iconButton()
            ->disabled($previous === null)
            ->url($previous ? $this->getRecordNavigationUrl($previous) : null)
            ->tooltip(__('core::core.previous'));

        // Next action (right arrow in LTR, left arrow in RTL)
        $actions[] = Action::make('nextRecord')
            ->icon($isRtl ? 'heroicon-o-chevron-left' : 'heroicon-o-chevron-right')
            ->color('gray')
            ->iconButton()
            ->disabled($next === null)
            ->url($next ? $this->getRecordNavigationUrl($next) : null)
            ->tooltip(__('core::core.next'));

        return $actions;
    }
}
