<?php

namespace App\Filament\Resources\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * Base RelationManager with lazy loading disabled for instant tab switching.
 */
abstract class BaseRelationManager extends RelationManager
{
    /**
     * Disable lazy loading so relation manager data loads with the page.
     * This enables instant tab switching without loading spinners.
     */
    protected static bool $isLazy = false;

    /**
     * The resource class for viewing records.
     * Set this to enable automatic ViewAction with URL navigation.
     *
     * Example: \Modules\Payroll\Filament\Resources\PayslipResource::class
     */
    protected static ?string $viewResource = null;

    /**
     * Get the URL for viewing a record.
     * Override this method for custom URL logic.
     */
    protected function getViewUrl(Model $record): ?string
    {
        if (static::$viewResource && method_exists(static::$viewResource, 'getUrl')) {
            try {
                return static::$viewResource::getUrl('view', ['record' => $record]);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get default table actions including ViewAction if viewResource is set.
     * Merge these with your custom actions in the table() method.
     */
    protected function getDefaultTableActions(): array
    {
        $actions = [];

        if (static::$viewResource) {
            $actions[] = Tables\Actions\ViewAction::make()
                ->url(fn (Model $record) => $this->getViewUrl($record));
        }

        return $actions;
    }
}
