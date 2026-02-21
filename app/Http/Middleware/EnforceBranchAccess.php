<?php

namespace App\Http\Middleware;

use App\Services\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Models\UserBranchRole;
use Modules\Core\Models\Branch;
use Symfony\Component\HttpFoundation\Response;

class EnforceBranchAccess
{
    /**
     * Handle an incoming request.
     *
     * Validates that the user has access to the branches they're trying to access.
     * Restricts branch context to only the branches the user is assigned to.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Skip for unauthenticated requests (handled elsewhere)
        if (!$user) {
            return $next($request);
        }

        // Skip for super admins (tenant owners)
        if ($this->isSuperAdmin($user)) {
            \Log::debug('EnforceBranchAccess: User is super admin, skipping', ['user_id' => $user->id]);
            return $next($request);
        }

        \Log::debug('EnforceBranchAccess: Checking branch access', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->toArray(),
        ]);

        // Get user's assigned branches
        $assignedBranchIds = $this->getUserAssignedBranchIds($user);

        // If user has no branch assignments, deny access
        if (empty($assignedBranchIds)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'no_branch_access',
                    'message' => __('core::core.errors.no_branch_assigned'),
                ], 403);
            }

            abort(403, __('core::core.errors.no_branch_assigned'));
        }

        // Get currently selected branches from context
        $currentBranchIds = BranchContext::currentIds();

        // If no branches selected (all branches mode), restrict to assigned branches
        if (empty($currentBranchIds)) {
            BranchContext::set($assignedBranchIds);
        } else {
            // Validate that selected branches are within assigned branches
            $unauthorizedBranches = array_diff($currentBranchIds, $assignedBranchIds);

            if (!empty($unauthorizedBranches)) {
                // Remove unauthorized branches from context
                $validBranches = array_intersect($currentBranchIds, $assignedBranchIds);

                if (empty($validBranches)) {
                    // Fall back to primary branch or first assigned branch
                    $primaryBranchId = $this->getUserPrimaryBranchId($user, $assignedBranchIds);
                    BranchContext::set([$primaryBranchId]);
                } else {
                    BranchContext::set($validBranches);
                }
            }
        }

        // Store assigned branches in request for later use
        $request->attributes->set('user_assigned_branches', $assignedBranchIds);

        return $next($request);
    }

    /**
     * Check if user is a super admin (has all-branch access).
     */
    protected function isSuperAdmin($user): bool
    {
        // Check for super admin role (check both hyphen and underscore variants)
        if ($user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        // Check for all_branches permission
        if ($user->can('access-all-branches')) {
            return true;
        }

        return false;
    }

    /**
     * Get the branch IDs that the user is assigned to.
     */
    protected function getUserAssignedBranchIds($user): array
    {
        return UserBranchRole::query()
            ->active()
            ->valid()
            ->forUser($user->id)
            ->pluck('branch_id')
            ->unique()
            ->toArray();
    }

    /**
     * Get user's primary branch ID.
     */
    protected function getUserPrimaryBranchId($user, array $assignedBranchIds): string
    {
        $primaryBranch = UserBranchRole::query()
            ->active()
            ->valid()
            ->primary()
            ->forUser($user->id)
            ->first();

        if ($primaryBranch) {
            return $primaryBranch->branch_id;
        }

        // Fall back to first assigned branch
        return $assignedBranchIds[0];
    }
}
