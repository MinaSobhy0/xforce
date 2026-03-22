<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Modules\Core\Models\Tenant;

/**
 * Trait EnforcesStorageQuota
 *
 * SECURITY: Enforces tenant storage quota to prevent resource exhaustion.
 * Without enforcement, tenants could exceed their plan limits indefinitely.
 *
 * Use this trait in Filament resources that handle file uploads.
 */
trait EnforcesStorageQuota
{
    /**
     * Check if tenant has remaining storage quota for an upload.
     * Returns false if quota would be exceeded.
     *
     * @param int $fileSizeBytes Size of file to upload in bytes
     * @param Tenant|null $tenant The tenant to check (defaults to current)
     * @return bool Whether the upload should be allowed
     */
    protected function hasStorageQuotaFor(int $fileSizeBytes, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? $this->getCurrentTenant();

        if (!$tenant) {
            // No tenant context - allow upload (likely platform admin)
            return true;
        }

        // Calculate file size in MB
        $fileSizeMb = ceil($fileSizeBytes / (1024 * 1024));

        // Get remaining storage
        $remainingMb = $tenant->getRemainingStorage();

        return $remainingMb >= $fileSizeMb;
    }

    /**
     * Get the current tenant from context.
     */
    protected function getCurrentTenant(): ?Tenant
    {
        // Try to get from tenant context service if available
        if (app()->bound('tenant')) {
            return app('tenant');
        }

        // Try to get from authenticated user
        $user = auth()->user();
        if ($user && method_exists($user, 'tenant')) {
            return $user->tenant;
        }

        return null;
    }

    /**
     * Get the storage quota limit for the current tenant in MB.
     */
    protected function getStorageQuotaMb(?Tenant $tenant = null): ?int
    {
        $tenant = $tenant ?? $this->getCurrentTenant();

        if (!$tenant) {
            return null; // No tenant = no limit
        }

        return $tenant->getEffectiveLimit('storage_mb');
    }

    /**
     * Get the remaining storage for the current tenant in MB.
     */
    protected function getRemainingStorageMb(?Tenant $tenant = null): int
    {
        $tenant = $tenant ?? $this->getCurrentTenant();

        if (!$tenant) {
            return PHP_INT_MAX; // No tenant = unlimited
        }

        return $tenant->getRemainingStorage();
    }

    /**
     * Get storage usage percentage for the current tenant.
     */
    protected function getStorageUsagePercentage(?Tenant $tenant = null): float
    {
        $tenant = $tenant ?? $this->getCurrentTenant();

        if (!$tenant) {
            return 0.0;
        }

        return $tenant->getUsagePercentage('storage_mb');
    }

    /**
     * Validate file upload against storage quota.
     * Returns array with validation result.
     *
     * @param int $fileSizeBytes Size of file(s) to upload in bytes
     * @return array ['allowed' => bool, 'message' => string|null, 'remaining_mb' => int]
     */
    protected function validateStorageQuota(int $fileSizeBytes): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [
                'allowed' => true,
                'message' => null,
                'remaining_mb' => PHP_INT_MAX,
            ];
        }

        $fileSizeMb = ceil($fileSizeBytes / (1024 * 1024));
        $remainingMb = $tenant->getRemainingStorage();
        $limitMb = $tenant->getEffectiveLimit('storage_mb');

        if ($remainingMb < $fileSizeMb) {
            $message = __('core::core.storage_quota_exceeded', [
                'remaining' => $remainingMb,
                'required' => $fileSizeMb,
                'limit' => $limitMb,
            ]);

            // Log the blocked upload attempt
            Log::warning('Storage quota exceeded - upload blocked', [
                'tenant_id' => $tenant->id,
                'remaining_mb' => $remainingMb,
                'requested_mb' => $fileSizeMb,
                'limit_mb' => $limitMb,
                'user_id' => auth()->id(),
            ]);

            return [
                'allowed' => false,
                'message' => $message,
                'remaining_mb' => $remainingMb,
            ];
        }

        // Check if nearing limit (80% usage warning)
        $usagePercent = $this->getStorageUsagePercentage($tenant);
        $warningMessage = null;

        if ($usagePercent >= 80 && $usagePercent < 100) {
            $warningMessage = __('core::core.storage_quota_warning', [
                'percentage' => round($usagePercent, 1),
                'remaining' => $remainingMb,
            ]);
        }

        return [
            'allowed' => true,
            'message' => $warningMessage,
            'remaining_mb' => $remainingMb,
            'usage_percent' => $usagePercent,
        ];
    }

    /**
     * Send storage quota notification.
     */
    protected function notifyStorageQuotaExceeded(int $requestedMb, int $remainingMb): void
    {
        Notification::make()
            ->title(__('core::core.storage_quota_exceeded_title'))
            ->body(__('core::core.storage_quota_exceeded_body', [
                'requested' => $requestedMb,
                'remaining' => $remainingMb,
            ]))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Send storage quota warning notification.
     */
    protected function notifyStorageQuotaWarning(float $usagePercent, int $remainingMb): void
    {
        Notification::make()
            ->title(__('core::core.storage_quota_warning_title'))
            ->body(__('core::core.storage_quota_warning_body', [
                'percentage' => round($usagePercent, 1),
                'remaining' => $remainingMb,
            ]))
            ->warning()
            ->send();
    }

    /**
     * Create a Filament FileUpload validation rule for storage quota.
     * Use this in FileUpload::make()->rules([...])
     */
    protected function storageQuotaRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (!$value) {
                return;
            }

            // Get file size
            $fileSize = 0;
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $fileSize = $value->getSize();
            } elseif (is_string($value) && file_exists($value)) {
                $fileSize = filesize($value);
            }

            if ($fileSize === 0) {
                return;
            }

            $result = $this->validateStorageQuota($fileSize);

            if (!$result['allowed']) {
                $fail($result['message']);
            }
        };
    }
}
