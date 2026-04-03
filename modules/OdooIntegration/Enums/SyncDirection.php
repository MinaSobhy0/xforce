<?php

namespace Modules\OdooIntegration\Enums;

enum SyncDirection: string
{
    case IMPORT = 'import';
    case EXPORT = 'export';
    case BIDIRECTIONAL = 'bidirectional';

    public function label(): string
    {
        return match($this) {
            self::IMPORT => 'Import (Odoo → XForce)',
            self::EXPORT => 'Export (XForce → Odoo)',
            self::BIDIRECTIONAL => 'Bidirectional',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::IMPORT => 'heroicon-o-arrow-down-tray',
            self::EXPORT => 'heroicon-o-arrow-up-tray',
            self::BIDIRECTIONAL => 'heroicon-o-arrows-right-left',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::IMPORT => 'info',
            self::EXPORT => 'success',
            self::BIDIRECTIONAL => 'warning',
        };
    }

    public function allowsImport(): bool
    {
        return in_array($this, [self::IMPORT, self::BIDIRECTIONAL]);
    }

    public function allowsExport(): bool
    {
        return in_array($this, [self::EXPORT, self::BIDIRECTIONAL]);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }
}
