<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Http\Middleware\IdentifyTenant;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ForceDeleteAction as TableForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register custom PostgreSQL connection that handles boolean types properly
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Sanctum to use our custom PersonalAccessToken model
        // This ensures tokens are stored in the public schema, not tenant schemas
        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Register morph map for polymorphic relationships
        // This handles legacy data with short names and makes URLs cleaner
        $this->registerMorphMap();

        // Add our custom IdentifyTenant middleware to Livewire's persistent middleware
        // This ensures the middleware runs on Livewire AJAX requests, not just initial page loads
        Livewire::addPersistentMiddleware([
            IdentifyTenant::class,
        ]);

        // Register Livewire components from modules
        $this->registerModuleLivewireComponents();

        // Configure delete actions to handle FK violations gracefully
        $this->configureDeleteActions();
    }

    /**
     * Configure all delete actions to catch FK violations and show friendly notifications.
     */
    protected function configureDeleteActions(): void
    {
        $handleFkViolation = function (QueryException $e): void {
            if ($e->getCode() === '23503') {
                // Extract table name from error message
                $table = 'related records';
                if (preg_match('/on table "([^"]+)"[^"]*$/', $e->getMessage(), $matches)) {
                    $table = str_replace('_', ' ', ucfirst($matches[1]));
                }

                Notification::make()
                    ->title(__('core::core.deletion_blocked.title'))
                    ->body(__('core::core.deletion_blocked.message', ['relation' => $table]))
                    ->danger()
                    ->persistent()
                    ->send();

                // Throw Halt to stop the action without showing success notification
                throw new Halt();
            }

            // Re-throw non-FK exceptions
            throw $e;
        };

        // Configure page/form delete actions
        DeleteAction::configureUsing(function (DeleteAction $action) use ($handleFkViolation): void {
            $action->using(function ($record) use ($handleFkViolation, $action) {
                try {
                    $record->delete();
                } catch (QueryException $e) {
                    $handleFkViolation($e);
                }
            });
        });

        ForceDeleteAction::configureUsing(function (ForceDeleteAction $action) use ($handleFkViolation): void {
            $action->using(function ($record) use ($handleFkViolation) {
                try {
                    $record->forceDelete();
                } catch (QueryException $e) {
                    $handleFkViolation($e);
                }
            });
        });

        // Configure table row delete actions
        TableDeleteAction::configureUsing(function (TableDeleteAction $action) use ($handleFkViolation): void {
            $action->using(function ($record) use ($handleFkViolation) {
                try {
                    $record->delete();
                } catch (QueryException $e) {
                    $handleFkViolation($e);
                }
            });
        });

        TableForceDeleteAction::configureUsing(function (TableForceDeleteAction $action) use ($handleFkViolation): void {
            $action->using(function ($record) use ($handleFkViolation) {
                try {
                    $record->forceDelete();
                } catch (QueryException $e) {
                    $handleFkViolation($e);
                }
            });
        });

        // Configure bulk delete actions
        DeleteBulkAction::configureUsing(function (DeleteBulkAction $action) use ($handleFkViolation): void {
            $action->using(function ($records) use ($handleFkViolation) {
                foreach ($records as $record) {
                    try {
                        $record->delete();
                    } catch (QueryException $e) {
                        $handleFkViolation($e);
                    }
                }
            });
        });

        ForceDeleteBulkAction::configureUsing(function (ForceDeleteBulkAction $action) use ($handleFkViolation): void {
            $action->using(function ($records) use ($handleFkViolation) {
                foreach ($records as $record) {
                    try {
                        $record->forceDelete();
                    } catch (QueryException $e) {
                        $handleFkViolation($e);
                    }
                }
            });
        });
    }

    /**
     * Register Livewire components from all modules.
     */
    protected function registerModuleLivewireComponents(): void
    {
        $modulesPath = base_path('modules');

        if (!is_dir($modulesPath)) {
            return;
        }

        // Register widgets
        foreach (glob($modulesPath . '/*/Filament/Widgets/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }

        // Register pages
        foreach (glob($modulesPath . '/*/Filament/Pages/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }

        // Register resource pages
        foreach (glob($modulesPath . '/*/Filament/Resources/*/Pages/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }

        // Register relation managers
        foreach (glob($modulesPath . '/*/Filament/Resources/*/RelationManagers/*.php') as $file) {
            $className = $this->getClassFromFile($file);
            if ($className && class_exists($className)) {
                $alias = $this->getComponentAlias($className);
                Livewire::component($alias, $className);
            }
        }
    }

    /**
     * Get fully qualified class name from file path.
     */
    protected function getClassFromFile(string $file): ?string
    {
        // Extract module name and class name from path
        // e.g., /var/www/html/x_linic/modules/Core/Filament/Widgets/TenantOverviewWidget.php
        if (preg_match('#modules/([^/]+)/(.+)\.php$#', $file, $matches)) {
            $module = $matches[1];
            $relativePath = $matches[2];
            $namespace = 'Modules\\' . $module . '\\' . str_replace('/', '\\', $relativePath);
            return $namespace;
        }
        return null;
    }

    /**
     * Get Livewire component alias from class name.
     */
    protected function getComponentAlias(string $className): string
    {
        // Convert Modules\Core\Filament\Widgets\TenantOverviewWidget
        // to modules.core.filament.widgets.tenant-overview-widget
        $alias = str_replace('\\', '.', $className);
        // Add hyphens before uppercase letters (while case is preserved)
        $alias = preg_replace('/([a-z])([A-Z])/', '$1-$2', $alias);
        // Now lowercase everything
        $alias = strtolower($alias);
        return $alias;
    }

    /**
     * Register morph map for polymorphic relationships.
     * Maps short names to fully qualified class names.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            // Accounting source types (legacy support)
            'vendor_payment' => \Modules\Inventory\Models\VendorBill::class,
            'vendor_bill' => \Modules\Inventory\Models\VendorBill::class,
            'invoice' => \Modules\Billing\Models\Invoice::class,
            'payment' => \Modules\Billing\Models\Payment::class,

            // Common partner types
            'patient' => \Modules\Patients\Models\Patient::class,
            'supplier' => \Modules\Inventory\Models\Supplier::class,
            'staff_profile' => \Modules\Staff\Models\StaffProfile::class,

            // Package types
            'package_subscription' => \Modules\Packages\Models\PackageSubscription::class,
            'package_session_usage' => \Modules\Packages\Models\PackageSessionUsage::class,
        ]);
    }
}