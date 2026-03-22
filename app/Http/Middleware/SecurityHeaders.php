<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to add security headers to all responses.
 * SECURITY: Provides defense-in-depth against XSS, clickjacking, MIME sniffing attacks.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS filter in browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy - don't leak URLs to other origins
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy - restrict powerful features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Only set HSTS in production with HTTPS
        if (app()->environment('production') && $request->secure()) {
            // Strict Transport Security - force HTTPS for 1 year
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy - restrictive by default
        // This helps prevent XSS attacks by controlling resource loading
        if (!$this->isApiRequest($request) && !$this->isLivewireRequest($request)) {
            $csp = $this->buildContentSecurityPolicy();
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    /**
     * Build Content Security Policy header value.
     */
    protected function buildContentSecurityPolicy(): string
    {
        $policies = [
            // Default to self
            "default-src 'self'",

            // Scripts - allow self, inline (for Alpine.js/Livewire), and specific CDNs
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com",

            // Styles - allow self, inline (for Tailwind), and Google Fonts
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",

            // Fonts
            "font-src 'self' https://fonts.gstatic.com data:",

            // Images - allow self, data URIs, and blob for cropper
            "img-src 'self' data: blob: https:",

            // Connect - API calls and websockets
            "connect-src 'self' ws: wss: https:",

            // Forms can only submit to self
            "form-action 'self'",

            // Only allow framing by same origin
            "frame-ancestors 'self'",

            // Base URI restricted to self
            "base-uri 'self'",

            // Object sources (plugins) disabled
            "object-src 'none'",
        ];

        return implode('; ', $policies);
    }

    /**
     * Check if this is an API request.
     */
    protected function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Check if this is a Livewire request.
     */
    protected function isLivewireRequest(Request $request): bool
    {
        return $request->is('livewire/*') || $request->hasHeader('X-Livewire');
    }
}
