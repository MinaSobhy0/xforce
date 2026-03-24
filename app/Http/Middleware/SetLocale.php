<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle locale query parameter for language switching
        if ($request->has('locale') && in_array($request->locale, ['en', 'ar'])) {
            session(['locale' => $request->locale]);

            return redirect()->to($request->url());
        }

        // Priority: Session > User preference > Browser > Default
        $locale = session('locale');

        if (!$locale) {
            try {
                if (auth()->check()) {
                    $locale = auth()->user()->language;
                }
            } catch (\Exception $e) {
                // Session may contain stale user ID (e.g., UUID from before migration)
                // Clear the auth session and continue
                auth()->logout();
                session()->invalidate();
                session()->regenerateToken();
            }
        }

        if (!$locale) {
            $locale = $request->getPreferredLanguage(['en', 'ar']);
        }

        if ($locale && in_array($locale, ['en', 'ar'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
