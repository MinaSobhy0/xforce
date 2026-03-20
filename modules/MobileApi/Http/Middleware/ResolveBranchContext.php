<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    /**
     * Handle an incoming request.
     * Resolves branch context from X-Branch-Id header or falls back to user's primary branch.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $branchId = $request->header('X-Branch-Id');
        $branch = null;

        if ($branchId) {
            // Validate user has access to the requested branch
            $hasAccess = $user->branches()
                ->where('branches.id', $branchId)
                ->exists();

            if ($hasAccess) {
                $branch = \Modules\Core\Models\Branch::find($branchId);
            }
        }

        // Fall back to user's primary branch
        if (!$branch) {
            $branch = $user->staffProfile?->branch
                ?? $user->branches()->first();
        }

        if ($branch) {
            app()->instance('currentBranch', $branch);

            // Set branch context for models that use it
            if (class_exists(\App\Contexts\BranchContext::class)) {
                \App\Contexts\BranchContext::set($branch);
            }
        }

        return $next($request);
    }
}
