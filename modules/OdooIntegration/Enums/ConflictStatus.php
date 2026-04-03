<?php

namespace Modules\OdooIntegration\Enums;

enum ConflictStatus: string
{
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending Review',
            self::RESOLVED => 'Resolved',
            self::DISMISSED => 'Dismissed',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'heroicon-o-exclamation-triangle',
            self::RESOLVED => 'heroicon-o-check-circle',
            self::DISMISSED => 'heroicon-o-x-mark',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::RESOLVED => 'success',
            self::DISMISSED => 'gray',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }
}
