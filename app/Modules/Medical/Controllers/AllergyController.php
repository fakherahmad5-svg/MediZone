<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\ResolvesPatientRecord;
use App\Models\Allergy;
use App\Modules\Medical\Requests\StoreAllergyRequest;
use App\Modules\Medical\Requests\UpdateAllergyRequest;
use App\Modules\Medical\Resources\AllergyResource;
use App\Modules\Medical\Services\MedicalHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AllergyController extends BaseController
{
    use ResolvesPatientRecord;

    public function __construct(
        private readonly MedicalHistoryService $medicalHistory
    ) {}

    public function index(Request $request): JsonResponse
    {
        $history = $this->historyFor($request);

        return $this->successResponse(
            AllergyResource::collection($history->allergies),
            'Allergies retrieved successfully.'
        );
    }

    public function store(StoreAllergyRequest $request): JsonResponse
    {
        $history = $this->historyFor($request);

        $allergy = $this->medicalHistory->addAllergy($history, $request->validated());

        return $this->createdResponse(
            new AllergyResource($allergy),
            'Allergy added successfully.'
        );
    }

    public function update(UpdateAllergyRequest $request, int $id): JsonResponse
    {
        $history = $this->historyFor($request);
        $allergy = Allergy::findOrFail($id);

        $updated = $this->medicalHistory->updateAllergy($history, $allergy, $request->validated());

        return $this->successResponse(
            new AllergyResource($updated),
            'Allergy updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $history = $this->historyFor($request);
        $allergy = Allergy::findOrFail($id);

        $this->medicalHistory->deleteAllergy($history, $allergy);

        return $this->noContentResponse();
    }

    private function historyFor(Request $request)
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        return $this->medicalHistory->forPatientRecord($patientRecord);
    }
}
