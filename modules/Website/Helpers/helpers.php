<?php

if (!function_exists('website_asset')) {
    /**
     * Generate a public URL for a website asset.
     *
     * @param string|array|null $path The asset path in tenant storage (can be array from FileUpload)
     * @return string|null The public URL
     */
    function website_asset(string|array|null $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Handle array (FileUpload can return arrays)
        if (is_array($path)) {
            // If it's an array, take the first element
            $path = reset($path);
            if (!$path) {
                return null;
            }
        }

        // Use the public website-assets route instead of tenant-storage
        return url('/website-assets/' . ltrim($path, '/'));
    }
}
