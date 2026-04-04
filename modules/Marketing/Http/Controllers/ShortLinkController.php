<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ShortLink;

class ShortLinkController extends Controller
{
    /**
     * Allowed external domains for redirects.
     * SECURITY: Only these domains are allowed for external redirects.
     */
    protected array $allowedExternalDomains = [
        'xforcehr.com',
        'xlinic.com',
    ];

    /**
     * Redirect short link to target URL.
     */
    public function redirect(string $code)
    {
        $link = ShortLink::where('code', $code)->first();

        if (!$link) {
            return response()->view('marketing::short-link-error', [
                'message' => __('marketing::marketing.short_links.not_found'),
            ], 404);
        }

        if ($link->isExpired()) {
            return response()->view('marketing::short-link-error', [
                'message' => __('marketing::marketing.short_links.expired'),
            ], 410);
        }

        // SECURITY: Validate the target URL to prevent open redirect attacks
        if (!$this->isUrlSafe($link->target_url)) {
            Log::warning('Short link redirect blocked - unsafe URL', [
                'code' => $code,
                'target_url' => $link->target_url,
                'ip' => request()->ip(),
            ]);
            return response()->view('marketing::short-link-error', [
                'message' => __('marketing::marketing.short_links.invalid_url'),
            ], 400);
        }

        // Record the click
        $link->recordClick();

        // Redirect to target URL
        return redirect($link->target_url);
    }

    /**
     * Check if a URL is safe to redirect to.
     * SECURITY: Prevents open redirect attacks by validating the target URL.
     *
     * @param string $url The URL to validate
     * @return bool True if the URL is safe to redirect to
     */
    protected function isUrlSafe(string $url): bool
    {
        // Parse the URL
        $parsed = parse_url($url);

        if ($parsed === false) {
            return false;
        }

        // Relative URLs are safe (internal)
        if (!isset($parsed['host'])) {
            // Block javascript: and data: URLs
            if (isset($parsed['scheme']) && in_array(strtolower($parsed['scheme']), ['javascript', 'data', 'vbscript'])) {
                return false;
            }
            return true;
        }

        $host = strtolower($parsed['host']);

        // Check if it's a subdomain of the app domain
        $appDomain = env('APP_DOMAIN', 'xforcehr.com');
        if ($host === $appDomain || str_ends_with($host, '.' . $appDomain)) {
            return true;
        }

        // Check against allowed external domains
        foreach ($this->allowedExternalDomains as $allowedDomain) {
            if ($host === $allowedDomain || str_ends_with($host, '.' . $allowedDomain)) {
                return true;
            }
        }

        // Block all other external URLs
        return false;
    }
}
