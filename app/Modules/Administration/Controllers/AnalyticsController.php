<?php

namespace App\Modules\Administration\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Administration\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AnalyticsController extends BaseController
{
    public function __construct(private readonly AnalyticsService $analytics)
    {
    }

    public function dashboard(): JsonResponse
    {
        return $this->successResponse($this->analytics->dashboard(), 'Dashboard summary retrieved successfully.');
    }

    public function appointmentStats(Request $request): JsonResponse
    {
        $stats = $this->analytics->appointmentStats(
            $request->input('from'),
            $request->input('to'),
            $request->integer('clinic_id') ?: null
        );

        return $this->successResponse($stats, 'Appointment statistics retrieved successfully.');
    }

    public function doctorPerformance(Request $request): JsonResponse
    {
        $performance = $this->analytics->doctorPerformance($request->integer('per_page', 20));

        return $this->paginatedResponse($performance, 'Doctor performance retrieved successfully.', fn ($row) => $row);
    }

    public function userEngagement(Request $request): JsonResponse
    {
        $stats = $this->analytics->userEngagement($request->input('from'), $request->input('to'));

        return $this->successResponse($stats, 'User engagement statistics retrieved successfully.');
    }

    public function reportsSummary(): JsonResponse
    {
        return $this->successResponse($this->analytics->reportsSummary(), 'Reports summary retrieved successfully.');
    }
}
