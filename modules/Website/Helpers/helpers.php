<?php

if (!function_exists('website_asset')) {
    /**
     * Generate a public URL for a website asset.
     *
     * @param string|null $path The asset path in tenant storage
     * @return string|null The public URL
     */
    function website_asset(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Use the public website-assets route instead of tenant-storage
        return url('/website-assets/' . ltrim($path, '/'));
    }
}
