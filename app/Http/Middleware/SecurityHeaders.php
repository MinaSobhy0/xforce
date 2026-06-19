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

        // SECURITY: Prevent clickjacking attacks - DENY is stricter than SAMEORIGIN
        // Use DENY to prevent any framing, even from same origin (more secure for healthcare data)
        $response->headers->set('X-Frame-Options', 'DENY');

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
            // Enforced policy — unchanged (keeps the unsafe-inline/unsafe-eval
            // that Livewire/Alpine currently need). This is what actually runs.
            $response->headers->set('Content-Security-Policy', $this->buildContentSecurityPolicy($request));

            // M-20 canary: a STRICT policy in Report-Only mode. The browser
            // reports what WOULD be blocked but blocks nothing — zero user impact
            // — so we can see exactly what must change before enforcing. Gated by
            // config('security.csp.report_only') so it can be switched off.
            if (config('security.csp.report_only')) {
                $response->headers->set(
                    'Content-Security-Policy-Report-Only',
                    $this->buildContentSecurityPolicy($request, reportOnly: true)
                );
            }
        }

        return $response;
    }

    /**
     * Build Content Security Policy header value.
     */
    protected function buildContentSecurityPolicy(Request $request, bool $reportOnly = false): string
    {
        // M-20: the strict (Report-Only) variant drops the unsafe-* allowances so
        // we can observe what would break before enforcing. The enforced policy
        // keeps them ($reportOnly = false).
        $scriptUnsafe = $reportOnly ? '' : " 'unsafe-inline' 'unsafe-eval'";
        $styleUnsafe = $reportOnly ? '' : " 'unsafe-inline'";


        $policies = [
            // Default to self
            "default-src 'self'",

            // Scripts - allow self, inline (for Alpine.js/Livewire), specific CDNs.
            // www.google.com + www.gstatic.com are required for Google reCAPTCHA v3
            // (used by the public contact forms).
            "script-src 'self'".$scriptUnsafe." https://cdn.jsdelivr.net https://unpkg.com https://www.google.com https://www.gstatic.com",

            // Styles - allow self, inline (for Tailwind), and Google Fonts
            "style-src 'self'".$styleUnsafe." https://fonts.googleapis.com",

            // Fonts
            "font-src 'self' https://fonts.gstatic.com data:",

            // Images - allow self, data URIs, and blob for cropper
            "img-src 'self' data: blob: https:",

            // Connect - API calls and websockets
            "connect-src 'self' ws: wss: https:",

            // Forms can only submit to self
            "form-action 'self'",

            // reCAPTCHA challenge iframes
            "frame-src 'self' https://www.google.com",

            // SECURITY: Disallow framing entirely (matches X-Frame-Options: DENY)
            "frame-ancestors 'none'",

            // Base URI restricted to self
            "base-uri 'self'",

            // Object sources (plugins) disabled
            "object-src 'none'",
        ];

        // M-20: collect violations from the strict policy so we can see what
        // would break across real users before enforcing.
        if ($reportOnly) {
            $policies[] = 'report-uri /csp-report';
        }

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
