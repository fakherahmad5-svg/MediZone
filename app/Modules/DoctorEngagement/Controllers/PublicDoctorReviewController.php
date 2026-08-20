<?php

namespace App\Modules\DoctorEngagement\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\DoctorEngagement\Resources\DoctorReviewResource;
use App\Modules\DoctorEngagement\Services\DoctorReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class PublicDoctorReviewController extends BaseController
{
    public function __construct(private readonly DoctorReviewService $reviews)
    {
    }

    public function index(Request $request, int $doctorId): JsonResponse
    {
        $reviews = $this->reviews->forDoctor($doctorId, $request->integer('per_page', 15));

        return $this->paginatedResponse(
            $reviews,
            'Doctor reviews retrieved successfully.',
            fn ($r) => (new DoctorReviewResource($r))->resolve($request)
        );
    }

    public function summary(int $doctorId): JsonResponse
    {
        return $this->successResponse(
            $this->reviews->summaryForDoctor($doctorId),
            'Doctor rating summary retrieved successfully.'
        );
    }
}
