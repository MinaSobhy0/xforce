<?php

namespace Modules\Projects\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;

enum KanbanState: string implements HasLabel, HasColor, HasIcon
{
    case NORMAL = 'normal';
    case BLOCKED = 'blocked';
    case DONE = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::NORMAL => __('projects::projects.kanban_state.normal'),
            self::BLOCKED => __('projects::projects.kanban_state.blocked'),
            self::DONE => __('projects::projects.kanban_state.done'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NORMAL => 'gray',
            self::BLOCKED => 'danger',
            self::DONE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::NORMAL => 'heroicon-o-minus-circle',
            self::BLOCKED => 'heroicon-o-exclamation-circle',
            self::DONE => 'heroicon-o-check-circle',
        };
    }

    /**
     * Get CSS class for the kanban state dot indicator.
     */
    public function getDotClass(): string
    {
        return match ($this) {
            self::NORMAL => 'bg-gray-400',
            self::BLOCKED => 'bg-red-500',
            self::DONE => 'bg-green-500',
        };
    }

    /**
     * Get hex color value for the kanban state.
     */
    public function getColorHex(): string
    {
        return match ($this) {
            self::NORMAL => '#9ca3af',
            self::BLOCKED => '#ef4444',
            self::DONE => '#10b981',
        };
    }

    /**
     * Check if task is blocked.
     */
    public function isBlocked(): bool
    {
        return $this === self::BLOCKED;
    }

    /**
     * Check if task is marked as ready.
     */
    public function isReady(): bool
    {
        return $this === self::DONE;
    }

    /**
     * Get the next state in the cycle.
     * Normal -> Done -> Blocked -> Normal
     */
    public function getNextState(): self
    {
        return match ($this) {
            self::NORMAL => self::DONE,
            self::DONE => self::BLOCKED,
            self::BLOCKED => self::NORMAL,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $state) => [$state->value => $state->getLabel()])
            ->toArray();
    }
}
