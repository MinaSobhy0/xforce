<?php

namespace App\Filament\Traits;

/**
 * Lets a Filament List page accept header actions contributed by other modules.
 *
 * Mirror of HasExtraRowActions but for page-level (header) actions returned by
 * \Filament\Resources\Pages\ListRecords::getHeaderActions().
 *
 * Usage on the List page:
 *   use App\Filament\Traits\HasExtraHeaderActions;
 *   ...
 *   protected function getHeaderActions(): array
 *   {
 *       return [
 *           Actions\CreateAction::make(),
 *           ...static::resolveExtraHeaderActions(),
 *           ...parent::getHeaderActions(),
 *       ];
 *   }
 */
trait HasExtraHeaderActions
{
    /** @var array<class-string, array<int, \Closure>> */
    protected static array $extraHeaderActionsByPage = [];

    public static function registerHeaderAction(\Closure $factory): void
    {
        static::$extraHeaderActionsByPage[static::class][] = $factory;
    }

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected static function resolveExtraHeaderActions(): array
    {
        $factories = static::$extraHeaderActionsByPage[static::class] ?? [];

        return array_values(array_map(fn (\Closure $f) => $f(), $factories));
    }
}
