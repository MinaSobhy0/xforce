<?php

namespace Modules\Projects\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;

enum TimesheetStatus: string implements HasLabel, HasColor, HasIcon
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('projects::projects.timesheet_status.draft'),
            self::SUBMITTED => __('projects::projects.timesheet_status.submitted'),
            self::APPROVED => __('projects::projects.timesheet_status.approved'),
            self::REJECTED => __('projects::projects.timesheet_status.rejected'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-pencil',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
            self::APPROVED => 'heroicon-o-check-badge',
            self::REJECTED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if the timesheet can be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED]);
    }

    /**
     * Check if the timesheet can be submitted.
     */
    public function canSubmit(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED]);
    }

    /**
     * Check if the timesheet can be approved/rejected.
     */
    public function canReview(): bool
    {
        return $this === self::SUBMITTED;
    }

    /**
     * Check if the status is terminal (final decision made).
     */
    public function isTerminal(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Get allowed transitions from current status.
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SUBMITTED],
            self::SUBMITTED => [self::APPROVED, self::REJECTED],
            self::REJECTED => [self::SUBMITTED],
            self::APPROVED => [], // Terminal state
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->getAllowedTransitions());
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->toArray();
    }

    /**
     * Get statuses that need manager attention.
     */
    public static function pendingReview(): array
    {
        return [self::SUBMITTED];
    }
}
