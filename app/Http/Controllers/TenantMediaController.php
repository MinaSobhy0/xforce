<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantMediaController extends Controller
{
    /**
     * Serve a file from tenant storage.
     * Route: /tenant-storage/{path}
     *
     * Tenant is identified via subdomain by IdentifyTenant middleware.
     * Requires authentication to ensure only tenant users can access files.
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        // Get the current tenant (set by IdentifyTenant middleware)
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (!$tenant) {
            abort(403, 'Tenant access required');
        }

        // Use the tenant disk (already configured by middleware)
        $disk = Storage::disk('tenant');

        if (!$disk->exists($path)) {
            abort(404, 'File not found');
        }

        $mimeType = $disk->mimeType($path);
        $size = $disk->size($path);

        return response()->stream(
            function () use ($disk, $path) {
                $stream = $disk->readStream($path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => $size,
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }
}
