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
        // Priority: Session > User preference > Browser > Default
        $locale = session('locale');

        if (!$locale && auth()->check()) {
            $locale = auth()->user()->language;
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
