<?php

namespace App\Modules\Patient\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Patient\Requests\UpdatePatientProfileRequest;
use App\Modules\Patient\Resources\PatientProfileResource;
use App\Modules\Patient\Services\PatientProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class PatientProfileController extends BaseController
{
    public function __construct(
        private readonly PatientProfileService $profiles
    ) {}

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new PatientProfileResource($this->profiles->forUser($request->user())),
            'Patient profile retrieved successfully.'
        );
    }

    public function update(UpdatePatientProfileRequest $request): JsonResponse
    {
        $patient = $this->profiles->forUser($request->user());

        $updated = $this->profiles->updateProfile($patient, $request->validated());

        return $this->successResponse(
            new PatientProfileResource($updated),
            'Profile updated successfully.'
        );
    }
}
