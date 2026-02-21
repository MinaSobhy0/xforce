<?php

namespace Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleActive
{
    /**
     * Handle an incoming request.
     *
     * @param  string|array  $modules
     */
    public function handle(Request $request, Closure $next, ...$modules): Response
    {
        if (!config('api.module_aware', true)) {
            return $next($request);
        }

        foreach ($modules as $module) {
            if (!$this->isModuleActive($module)) {
                return response()->json([
                    'success' => false,
                    'message' => __('api::api.module_not_active', ['module' => $module]),
                ], 404);
            }
        }

        return $next($request);
    }

    protected function isModuleActive(string $module): bool
    {
        // Check if module is enabled via nwidart/laravel-modules
        if (class_exists(\Nwidart\Modules\Facades\Module::class)) {
            $moduleInstance = \Nwidart\Modules\Facades\Module::find($module);
            return $moduleInstance && $moduleInstance->isEnabled();
        }

        // Fallback: check if module directory exists
        return is_dir(base_path("modules/{$module}"));
    }
}
