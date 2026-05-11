<?php

namespace Modules\OdooIntegration\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Services\Sync\SyncEngine;

/**
 * Page-level "Push to Odoo" action — pushes every local row that has no odoo_id
 * and matches an active OdooEntityMapping for the page's resource model.
 *
 * Visible only when:
 *   1. an active mapping exists for the resource's model,
 *   2. its sync_direction permits export, and
 *   3. there is at least one record with odoo_id IS NULL.
 *
 * Each pushed row gets its odoo_id written back by ExportService, so it drops
 * out of the unlinked set automatically.
 */
class PushUnlinkedToOdooAction
{
    /**
     * @param class-string $modelClass The resource's Eloquent model.
     */
    public static function make(string $modelClass): Action
    {
        return Action::make('push_unlinked_to_odoo')
            ->label(__('odoo-integration::odoo.actions.push_to_odoo'))
            ->icon('heroicon-o-arrow-up-on-square')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('odoo-integration::odoo.actions.push_to_odoo'))
            ->modalDescription(fn () => __('odoo-integration::odoo.messages.push_unlinked_confirm', [
                'count' => static::countUnlinked($modelClass),
            ]))
            ->visible(fn () => static::isVisibleFor($modelClass))
            ->action(fn () => static::run($modelClass));
    }

    protected static function isVisibleFor(string $modelClass): bool
    {
        if (! static::resolveMapping($modelClass)) {
            return false;
        }

        return static::countUnlinked($modelClass) > 0;
    }

    protected static function countUnlinked(string $modelClass): int
    {
        if (! class_exists($modelClass)) {
            return 0;
        }

        return $modelClass::query()->whereNull('odoo_id')->count();
    }

    protected static function run(string $modelClass): void
    {
        $mapping = static::resolveMapping($modelClass);
        if (! $mapping) {
            Notification::make()
                ->title(__('odoo-integration::odoo.messages.no_mapping'))
                ->danger()
                ->send();

            return;
        }

        // Which relations on this mapping must resolve to an Odoo id?
        // We skip records whose required relation isn't linked yet, rather than
        // round-tripping to Odoo only to have it reject the create.
        $requiredRelations = $mapping->getActiveFieldMappings()
            ->filter(fn ($fm) => $fm->transform_type === 'relation' && $fm->allowsExport())
            ->map(fn ($fm) => [
                'local_field' => $fm->local_field,
                'model' => $fm->transform_config['model'] ?? null,
            ])
            ->filter(fn ($r) => $r['model'] && class_exists($r['model']))
            ->all();

        $syncEngine = app(SyncEngine::class);
        $ids = $modelClass::query()->whereNull('odoo_id')->pluck('id');
        $pushed = 0;
        $failed = 0;
        $skipped = 0;
        $firstError = null;
        $skippedExample = null;

        foreach ($ids as $id) {
            // Pre-flight: skip when a required relation has no odoo_id locally.
            $missing = static::findMissingRelations($modelClass, $id, $requiredRelations);
            if ($missing !== null) {
                $skipped++;
                $skippedExample ??= __('odoo-integration::odoo.messages.push_skipped_reason', [
                    'id' => $id,
                    'field' => $missing['local_field'],
                    'related_id' => $missing['related_id'],
                ]);

                continue;
            }

            try {
                $syncEngine->syncRecord(mapping: $mapping, localId: $id, direction: 'export');
                $pushed++;
            } catch (\Throwable $e) {
                $failed++;
                $firstError ??= "id $id: ".$e->getMessage();
            }
        }

        $notification = Notification::make()
            ->title(__('odoo-integration::odoo.messages.push_bulk_done'))
            ->body(__('odoo-integration::odoo.messages.push_bulk_body', [
                'pushed' => $pushed,
                'skipped' => $skipped,
                'failed' => $failed,
            ]));

        if ($failed > 0) {
            $notification->danger();
        } elseif ($skipped > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $detail = $firstError ?? $skippedExample;
        if ($detail) {
            $notification->body($notification->getBody()."\n".$detail);
        }

        $notification->send();
    }

    /**
     * Return the first required relation on the record that isn't linked to Odoo
     * yet (related row exists locally but has no odoo_id). Null when all relations
     * are resolvable.
     *
     * @param  array<int, array{local_field: string, model: class-string}>  $relations
     * @return array{local_field: string, related_id: int}|null
     */
    protected static function findMissingRelations(string $modelClass, int $id, array $relations): ?array
    {
        if (empty($relations)) {
            return null;
        }

        $record = $modelClass::query()->find($id);
        if (! $record) {
            return null;
        }

        foreach ($relations as $rel) {
            $relatedId = $record->{$rel['local_field']} ?? null;
            if (! $relatedId) {
                continue;
            }

            $hasOdooId = $rel['model']::query()
                ->whereKey($relatedId)
                ->whereNotNull('odoo_id')
                ->exists();

            if (! $hasOdooId) {
                return ['local_field' => $rel['local_field'], 'related_id' => $relatedId];
            }
        }

        return null;
    }

    protected static function resolveMapping(string $modelClass): ?OdooEntityMapping
    {
        static $cache = [];
        if (array_key_exists($modelClass, $cache)) {
            return $cache[$modelClass];
        }

        $mapping = OdooEntityMapping::query()
            ->where('local_model', $modelClass)
            ->where('is_active', true)
            ->get()
            ->first(fn (OdooEntityMapping $m) => $m->sync_direction->allowsExport());

        return $cache[$modelClass] = $mapping;
    }
}
