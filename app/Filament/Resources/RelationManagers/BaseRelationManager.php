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

    /**
     * Check if the current user can edit the parent resource.
     * Uses the parent resource's permission key.
     */
    protected function canEditParentResource(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // Super admin and key roles always have access
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        // Get the parent resource class from the page component
        $pageClass = $this->getPageClass();
        if ($pageClass && method_exists($pageClass, 'getResource')) {
            $resourceClass = $pageClass::getResource();

            // Use getter method if available (from ChecksResourcePermissions trait)
            if (method_exists($resourceClass, 'getPermissionKey')) {
                $permissionKey = $resourceClass::getPermissionKey();
            } else {
                // Fallback: derive from resource name
                $permissionKey = strtolower(str_replace('Resource', '', class_basename($resourceClass)));
            }

            return $user->can("{$permissionKey}.edit");
        }

        return false;
    }
}
