<?php

namespace XLinic\Framework\Core\Model\Traits;

use Spatie\ModelStates\HasStates;
use Illuminate\Database\Eloquent\Builder;

trait HasStateMachine
{
    use HasStates;

    /**
     * Boot the HasStateMachine trait.
     */
    protected static function bootHasStateMachine(): void
    {
        // Log state transitions
        static::saved(function ($model) {
            if ($model->wasChanged($model->getStateColumn()) && $model->wasRecentlyCreated === false) {
                $from = $model->getOriginal($model->getStateColumn());
                $to = $model->getAttribute($model->getStateColumn());

                activity()
                    ->performedOn($model)
                    ->withProperties([
                        'from_state' => $from,
                        'to_state' => $to,
                    ])
                    ->log("State changed from {$from} to {$to}");
            }
        });
    }

    /**
     * Get the state column name.
     */
    protected function getStateColumn(): string
    {
        return $this->stateColumn ?? 'state';
    }

    /**
     * Check if the model can transition to a specific state.
     */
    public function canTransitionTo(string $stateClass): bool
    {
        return $this->state->canTransitionTo($stateClass);
    }

    /**
     * Get all possible next states.
     */
    public function getPossibleNextStates(): array
    {
        if (!method_exists($this->state, 'transitions')) {
            return [];
        }

        return $this->state->transitions();
    }

    /**
     * Transition to a new state with validation.
     */
    public function transitionTo(string $stateClass, array $customProperties = []): bool
    {
        if (!$this->canTransitionTo($stateClass)) {
            return false;
        }

        $oldState = $this->state;

        $this->state->transitionTo($stateClass, $customProperties);

        // Fire transition event
        if (method_exists($this, 'fireStateTransitionEvent')) {
            $this->fireStateTransitionEvent($oldState, $this->state);
        }

        return $this->save();
    }

    /**
     * Scope query to specific state.
     */
    public function scopeInState(Builder $query, $state): Builder
    {
        return $query->whereState($this->getStateColumn(), $state);
    }

    /**
     * Scope query to exclude specific states.
     */
    public function scopeNotInState(Builder $query, $states): Builder
    {
        return $query->whereNotState($this->getStateColumn(), $states);
    }

    /**
     * Get state history.
     */
    public function getStateHistory()
    {
        return $this->activities()
            ->whereNotNull('properties->from_state')
            ->whereNotNull('properties->to_state')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the display name for the current state.
     */
    public function getStateDisplayName(): string
    {
        if (method_exists($this->state, 'getDisplayName')) {
            return $this->state->getDisplayName();
        }

        return class_basename($this->state);
    }

    /**
     * Get the color for the current state (for UI).
     */
    public function getStateColor(): string
    {
        if (method_exists($this->state, 'getColor')) {
            return $this->state->getColor();
        }

        return 'gray';
    }

    /**
     * Get the icon for the current state (for UI).
     */
    public function getStateIcon(): string
    {
        if (method_exists($this->state, 'getIcon')) {
            return $this->state->getIcon();
        }

        return 'heroicon-o-circle-stack';
    }
}