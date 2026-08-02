<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Return a success response.
     */
    protected function success($data = null, string $message = null, int $code = 200): JsonResponse
    {
        $message = $message ?? __('mobile_api::mobile.general.success');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return an error response.
     *
     * $errorCode is a machine-readable slug the mobile client can key on
     * to make decisions without parsing the human message
     * (e.g. INSUFFICIENT_BALANCE, OUT_OF_GEOFENCE). Prefer 422 for
     * business-rule rejections and pass an errorCode.
     */
    protected function error(string $message, int $code = 400, $errors = null, ?string $errorCode = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Convenience for the {success:false, message, error_code} envelope at
     * HTTP 422 the mobile app uses for business-rule rejections.
     */
    protected function businessRuleError(string $message, string $errorCode): JsonResponse
    {
        return $this->error($message, 422, null, $errorCode);
    }

    /**
     * Return a validation error response.
     */
    protected function validationError($errors): JsonResponse
    {
        return $this->error(
            __('mobile_api::mobile.general.validation_error'),
            422,
            $errors
        );
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorized(string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? __('mobile_api::mobile.general.unauthorized'),
            401
        );
    }

    /**
     * Return a forbidden response.
     */
    protected function forbidden(string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? __('mobile_api::mobile.general.forbidden'),
            403
        );
    }

    /**
     * Return a not found response.
     */
    protected function notFound(string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? __('mobile_api::mobile.general.not_found'),
            404
        );
    }

    /**
     * Return a paginated response.
     */
    protected function paginated($paginator, $resource = null): JsonResponse
    {
        $data = $resource
            ? $resource::collection($paginator->items())
            : $paginator->items();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get per page value from request.
     */
    protected function getPerPage(): int
    {
        $perPage = request()->input('per_page', config('mobile_api.pagination.default_per_page', 15));
        $maxPerPage = config('mobile_api.pagination.max_per_page', 100);

        return min((int) $perPage, $maxPerPage);
    }

    /**
     * Get the authenticated user.
     */
    protected function user()
    {
        return auth()->user();
    }

    /**
     * Get the staff profile of the authenticated user.
     */
    protected function staffProfile()
    {
        return $this->user()?->staffProfile;
    }

    /**
     * Get the current tenant.
     */
    protected function tenant()
    {
        return app('currentTenant');
    }

    /**
     * Get the current branch context.
     */
    protected function branch()
    {
        // currentBranch is only bound by ResolveBranchContext when the user
        // has a branch — resolving it unbound throws for branchless staff.
        return (app()->bound('currentBranch') ? app('currentBranch') : null)
            ?? $this->staffProfile()?->branch;
    }

    /**
     * Check if the user has a specific permission.
     */
    protected function hasPermission(string $permission): bool
    {
        return $this->user()?->can($permission) ?? false;
    }

    /**
     * Require a specific permission or throw forbidden.
     */
    protected function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            abort(403, __('mobile_api::mobile.general.forbidden'));
        }
    }
}
