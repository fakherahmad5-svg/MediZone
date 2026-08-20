<?php

namespace App\Modules\DoctorEngagement\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\DoctorEngagement\Resources\DoctorFavoriteResource;
use App\Modules\DoctorEngagement\Services\DoctorFavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientFavoriteController extends BaseController
{
    public function __construct(private readonly DoctorFavoriteService $favorites)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $favorites = $this->favorites->listForPatient($request->user());

        return $this->successResponse(
            DoctorFavoriteResource::collection($favorites),
            'Favorite doctors retrieved successfully.'
        );
    }

    public function toggle(Request $request, int $doctorId): JsonResponse
    {
        $result = $this->favorites->toggle($request->user(), $doctorId);

        return $this->successResponse($result, $result['is_favorite']
            ? 'Doctor added to favorites.'
            : 'Doctor removed from favorites.');
    }
}
