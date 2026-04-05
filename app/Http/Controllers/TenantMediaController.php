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

        // SECURITY: Prevent path traversal attacks
        if (!$this->isPathSafe($path)) {
            \Illuminate\Support\Facades\Log::warning('Path traversal attempt blocked', [
                'path' => $path,
                'tenant_id' => $tenant->id ?? null,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
            abort(400, 'Invalid file path');
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

    /**
     * Serve public website assets (logos, images, etc.) from tenant storage.
     * Route: /website-assets/{path}
     *
     * This is for public website content only - no auth required.
     * Only allows access to website-related directories.
     */
    public function showPublic(Request $request, string $path): StreamedResponse
    {
        // Get the current tenant (set by IdentifyTenant middleware)
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (!$tenant) {
            abort(403, 'Tenant access required');
        }

        // SECURITY: Prevent path traversal attacks
        if (!$this->isPathSafe($path)) {
            \Illuminate\Support\Facades\Log::warning('Path traversal attempt blocked in public assets', [
                'path' => $path,
                'tenant_id' => $tenant->id ?? null,
                'ip' => $request->ip(),
            ]);
            abort(400, 'Invalid file path');
        }

        // SECURITY: Only allow access to website-related directories
        $allowedPrefixes = ['website/', 'logos/', 'favicons/'];
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            abort(403, 'Access denied to this directory');
        }

        // Use the tenant disk
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
                'Cache-Control' => 'public, max-age=86400', // 24 hours cache for public assets
            ]
        );
    }

    /**
     * SECURITY: Check if a path is safe (no directory traversal).
     */
    protected function isPathSafe(string $path): bool
    {
        // Reject paths with null bytes
        if (str_contains($path, "\0")) {
            return false;
        }

        // Reject paths with directory traversal patterns
        if (preg_match('/\.\.[\\/]|[\\/]\.\./', $path)) {
            return false;
        }

        // Reject absolute paths
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return false;
        }

        // Reject Windows-style drive letters
        if (preg_match('/^[a-zA-Z]:/', $path)) {
            return false;
        }

        // Normalize and check the path
        $normalized = str_replace('\\', '/', $path);
        $parts = explode('/', $normalized);

        $depth = 0;
        foreach ($parts as $part) {
            if ($part === '..') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            } elseif ($part !== '' && $part !== '.') {
                $depth++;
            }
        }

        return true;
    }
}
