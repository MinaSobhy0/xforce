<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Auth\Models\Permission;

/**
 * Permissions for the WhatsApp Inbox resource (Phase C).
 *
 * Without these the resource's ChecksResourcePermissions trait hides the
 * nav entry from regular users. Super admins and tenant owners bypass
 * the check, so the resource is already visible to them — these rows
 * just let clinic staff with the granted role open it too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'whatsapp_conversations.view_any', 'guard_name' => 'web'],
            ['display_name' => 'View WhatsApp Inbox', 'module' => 'marketing']
        );

        Permission::firstOrCreate(
            ['name' => 'whatsapp_conversations.view', 'guard_name' => 'web'],
            ['display_name' => 'Open WhatsApp Conversation', 'module' => 'marketing']
        );

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'whatsapp_conversations.view_any',
                'whatsapp_conversations.view',
            ])
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
