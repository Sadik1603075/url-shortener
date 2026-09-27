<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    /**
     * GET /api/v1/admin/analytics/overview
     *
     * Aggregate figures + activity series for the admin dashboard.
     */
    public function overview(): JsonResponse
    {
        $days = (int) request('days', 30);
        $days = max(7, min($days, 90));

        return response()->json([
            'data' => $this->analytics->overview($days),
        ]);
    }
}
