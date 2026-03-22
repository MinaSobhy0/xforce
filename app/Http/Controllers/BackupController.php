<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Download a backup file.
     *
     * SECURITY: Only super_admin and platform_admin roles can download backups.
     * Backup files contain sensitive database dumps that must be protected.
     */
    public function download(Request $request, Backup $backup): StreamedResponse
    {
        // SECURITY: Verify user is authenticated and has backup download permission
        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentication required');
        }

        // Check if user has the required role to download backups
        $allowedRoles = ['super_admin', 'platform_admin'];
        $hasPermission = false;

        if (method_exists($user, 'hasRole')) {
            $hasPermission = $user->hasRole($allowedRoles);
        }

        // Also check for specific permission if role check fails
        if (!$hasPermission && method_exists($user, 'hasPermissionTo')) {
            $hasPermission = $user->hasPermissionTo('backup.download');
        }

        if (!$hasPermission) {
            // Log unauthorized access attempt
            Log::warning('Unauthorized backup download attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'backup_id' => $backup->id,
                'ip' => $request->ip(),
            ]);
            abort(403, 'You do not have permission to download backups');
        }

        // Check if backup exists and is completed
        if ($backup->status !== 'completed') {
            abort(404, 'Backup not found or not completed');
        }

        if (!$backup->path || !Storage::disk($backup->disk)->exists($backup->path)) {
            abort(404, 'Backup file not found');
        }

        // Log successful backup download for audit trail
        Log::info('Backup downloaded', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'backup_id' => $backup->id,
            'tenant_id' => $backup->tenant_id ?? null,
            'ip' => $request->ip(),
        ]);

        $filename = $backup->filename ?? basename($backup->path);

        return Storage::disk($backup->disk)->download($backup->path, $filename);
    }
}
