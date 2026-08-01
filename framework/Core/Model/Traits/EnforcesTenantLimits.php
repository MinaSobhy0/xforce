<?php

namespace XLinic\Framework\Core\Model\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait EnforcesTenantLimits
 *
 * SECURITY: Enforces tenant resource limits at the model level.
 * This prevents bypassing HTTP middleware when creating records programmatically.
 *
 * Usage: Add this trait to models that have tenant limits (User, Branch, Patient)
 * and define the $tenantLimitField property.
 */
trait EnforcesTenantLimits
{
    /**
     * Boot the trait - register creating event.
     */
    public static function bootEnforcesTenantLimits(): void
    {
        static::creating(function (Model $model) {
            $model->enforceLimit();
        });
    }

    /**
     * Get the tenant limit field name for this model.
     * Override in model if needed.
     */
    protected function getTenantLimitField(): ?string
    {
        return $this->tenantLimitField ?? null;
    }

    /**
     * Get the extra limit field name for this model.
     */
    protected function getTenantExtraField(): ?string
    {
        $limitField = $this->getTenantLimitField();
        if (! $limitField) {
            return null;
        }

        // Convert max_users -> extra_users, max_branches -> extra_branches
        return str_replace('max_', 'extra_', $limitField);
    }

    /**
     * Enforce the tenant limit.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \RuntimeException
     */
    protected function enforceLimit(): void
    {
        $limitField = $this->getTenantLimitField();

        if (! $limitField) {
            return; // No limit defined for this model
        }

        $tenant = $this->getTenantForLimitCheck();

        if (! $tenant) {
            return; // No tenant context
        }

        $maxAllowed = $this->resolveEffectiveLimit($tenant, $limitField);

        // If limit is 0 or not set, allow unlimited
        if ($maxAllowed <= 0) {
            return;
        }

        $currentCount = $this->countExistingRecords();

        if ($currentCount >= $maxAllowed) {
            $resourceName = $this->getResourceNameForLimit();

            \Log::warning('Tenant limit exceeded at model level', [
                'tenant_id' => $tenant->id,
                'model' => static::class,
                'resource' => $resourceName,
                'current' => $currentCount,
                'max' => $maxAllowed,
            ]);

            $message = __('Cannot create :resource. You have reached the maximum of :max allowed for your plan (currently: :current).', [
                'resource' => $resourceName,
                'max' => $maxAllowed,
                'current' => $currentCount,
            ]);

            // Inside a Filament panel: show a danger toast and halt the
            // action cleanly. Halt is the Filament idiom for "abort without
            // a server error" — the form stays open, no 500 page renders.
            // The hard cap is still enforced (no record created); only the
            // UX changes from "500 error" to "polite toast".
            //
            // Outside Filament (CLI seeders, queue jobs, API requests, any
            // programmatic path), keep the RuntimeException so existing
            // callers can catch it the way they already do.
            if ($this->isFilamentContext()) {
                if (class_exists(\Filament\Notifications\Notification::class)) {
                    \Filament\Notifications\Notification::make()
                        ->title($message)
                        ->body(__('Upgrade your plan or remove unused records to add more.'))
                        ->danger()
                        ->persistent()
                        ->send();
                }

                if (class_exists(\Filament\Support\Exceptions\Halt::class)) {
                    throw new \Filament\Support\Exceptions\Halt;
                }
            }

            throw new \RuntimeException($message);
        }
    }

    /**
     * Best-effort check for whether the current request is being handled
     * by a Filament panel. We use this to decide between a friendly
     * toast+Halt (panel) and a raw RuntimeException (CLI/API/jobs).
     */
    protected function isFilamentContext(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        if (function_exists('filament')) {
            try {
                return filament()->getCurrentPanel() !== null;
            } catch (\Throwable $e) {
                // Filament not fully booted yet — treat as non-panel.
                return false;
            }
        }

        return false;
    }

    /**
     * Get the tenant for limit checking.
     */
    protected function getTenantForLimitCheck(): ?object
    {
        if (app()->bound('currentTenant')) {
            return app('currentTenant');
        }

        return null;
    }

    /**
     * Resolve the effective cap for this resource.
     *
     * Prefers the Tenant model's `getEffectiveLimit($resource)` method
     * (which correctly returns `plan.max_users + tenant.extra_users` —
     * the same number shown on the Usage dashboard). Falls back to the
     * legacy `tenant.max_users + tenant.extra_users` shape only when
     * the tenant object doesn't expose the helper, so older test
     * doubles and fixtures keep working.
     *
     * This was the source of the "dashboard says 25, creation says 15"
     * mismatch: the dashboard used the plan-aware getter, the trait
     * read the raw column (which can be stale when the plan changes
     * without a corresponding update on the tenant row).
     */
    protected function resolveEffectiveLimit(object $tenant, string $limitField): int
    {
        // Derive the resource key the Tenant model expects ('users',
        // 'branches', ...) from the field name ('max_users' → 'users')
        // or from the configured resource name on the model.
        $resource = $this->tenantLimitResourceName
            ?? str_replace('max_', '', $limitField);

        if (method_exists($tenant, 'getEffectiveLimit')) {
            return (int) ($tenant->getEffectiveLimit($resource) ?? 0);
        }

        // Legacy fallback — same shape as before.
        $baseLimit = (int) ($tenant->{$limitField} ?? 0);
        $extraField = $this->getTenantExtraField();
        $extraLimit = $extraField ? (int) ($tenant->{$extraField} ?? 0) : 0;

        return $baseLimit + $extraLimit;
    }

    /**
     * Count existing records for this model type.
     */
    protected function countExistingRecords(): int
    {
        return static::count();
    }

    /**
     * Get human-readable resource name for error messages.
     */
    protected function getResourceNameForLimit(): string
    {
        return $this->tenantLimitResourceName ?? strtolower(class_basename(static::class));
    }

    /**
     * Check if creating a new record would exceed the limit.
     * Useful for checking before attempting to create.
     */
    public static function wouldExceedLimit(): bool
    {
        $instance = new static;

        try {
            $instance->enforceLimit();

            return false;
        } catch (\RuntimeException $e) {
            return true;
        }
    }

    /**
     * Get remaining capacity for this resource type.
     */
    public static function getRemainingCapacity(): int
    {
        $instance = new static;
        $limitField = $instance->getTenantLimitField();

        if (! $limitField) {
            return PHP_INT_MAX;
        }

        $tenant = $instance->getTenantForLimitCheck();

        if (! $tenant) {
            return PHP_INT_MAX;
        }

        $maxAllowed = $instance->resolveEffectiveLimit($tenant, $limitField);

        if ($maxAllowed <= 0) {
            return PHP_INT_MAX;
        }

        $currentCount = static::count();

        return max(0, $maxAllowed - $currentCount);
    }
}
