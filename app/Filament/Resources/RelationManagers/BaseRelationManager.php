<?php

namespace App\Filament\Resources\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

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
}
