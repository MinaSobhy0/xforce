<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\MobileApi\Services\SDUIService;

class ScreenController extends BaseApiController
{
    public function __construct(
        protected SDUIService $sduiService
    ) {}

    /**
     * Get screen definition.
     * GET /api/v2/screens/{screen}
     */
    public function show(string $screen): JsonResponse
    {
        $user = $this->user();

        $screenData = $this->sduiService->buildScreen($screen, $user);

        if (empty($screenData)) {
            return $this->notFound('Screen not found');
        }

        return $this->success($screenData);
    }

    /**
     * Get screen data only (for refresh).
     * GET /api/v2/screens/{screen}/data
     */
    public function data(string $screen): JsonResponse
    {
        $user = $this->user();

        $data = $this->sduiService->getScreenData($screen, $user);

        return $this->success($data);
    }
}
