<?php

namespace App\Modules\DoctorEngagement\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Modules\DoctorEngagement\Requests\SubmitReviewRequest;
use App\Modules\DoctorEngagement\Resources\DoctorReviewResource;
use App\Modules\DoctorEngagement\Services\DoctorReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class PatientReviewController extends BaseController
{
    public function __construct(private readonly DoctorReviewService $reviews)
    {
    }

    public function show(Request $request, int $doctorId): JsonResponse
    {
        $review = $this->reviews->myReview($request->user(), $doctorId);

        return $this->successResponse(
            $review ? new DoctorReviewResource($review) : null,
            $review ? 'Your review retrieved successfully.' : 'You have not reviewed this doctor yet.'
        );
    }


    public function store(SubmitReviewRequest $request, int $doctorId): JsonResponse
    {
        $review = $this->reviews->submit(
            $request->user(),
            $doctorId,
            $request->validated('rating'),
            $request->validated('comment')
        );

        return $this->successResponse(new DoctorReviewResource($review), 'Review submitted successfully.');
    }

    public function destroy(Request $request, int $doctorId): JsonResponse
    {
        $this->reviews->delete($request->user(), $doctorId);

        return $this->successResponse(message: 'Review removed successfully.');
    }
}
