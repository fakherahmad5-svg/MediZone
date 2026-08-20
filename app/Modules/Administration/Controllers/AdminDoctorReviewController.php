<?php

namespace App\Modules\Administration\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\DoctorEngagement\Resources\DoctorReviewResource;
use App\Modules\DoctorEngagement\Services\DoctorReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AdminDoctorReviewController extends BaseController
{
    public function __construct(private readonly DoctorReviewService $reviews)
    {
    }

    public function index(Request $request, int $doctorId): JsonResponse
    {
        $reviews = $this->reviews->forDoctor($doctorId, $request->integer('per_page', 20));

        return $this->paginatedResponse(
            $reviews,
            'Doctor reviews retrieved successfully.',
            fn ($r) => (new DoctorReviewResource($r))->resolve($request)
        );
    }
}
