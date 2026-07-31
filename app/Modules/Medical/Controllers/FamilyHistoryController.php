<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\ResolvesPatientRecord;
use App\Models\FamilyHistory;
use App\Modules\Medical\Requests\StoreFamilyHistoryRequest;
use App\Modules\Medical\Requests\UpdateFamilyHistoryRequest;
use App\Modules\Medical\Resources\FamilyHistoryResource;
use App\Modules\Medical\Services\MedicalHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class FamilyHistoryController extends BaseController
{
    use ResolvesPatientRecord;

    public function __construct(
        private readonly MedicalHistoryService $medicalHistory
    ) {}

    public function index(Request $request): JsonResponse
    {
        $history = $this->historyFor($request);

        return $this->successResponse(
            FamilyHistoryResource::collection($history->familyHistories),
            'Family history retrieved successfully.'
        );
    }

    public function store(StoreFamilyHistoryRequest $request): JsonResponse
    {
        $history = $this->historyFor($request);

        $entry = $this->medicalHistory->addFamilyHistory($history, $request->validated());

        return $this->createdResponse(
            new FamilyHistoryResource($entry),
            'Family history entry added successfully.'
        );
    }

    public function update(UpdateFamilyHistoryRequest $request, int $id): JsonResponse
    {
        $history = $this->historyFor($request);
        $entry   = FamilyHistory::findOrFail($id);

        $updated = $this->medicalHistory->updateFamilyHistory($history, $entry, $request->validated());

        return $this->successResponse(
            new FamilyHistoryResource($updated),
            'Family history entry updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $history = $this->historyFor($request);
        $entry   = FamilyHistory::findOrFail($id);

        $this->medicalHistory->deleteFamilyHistory($history, $entry);

        return $this->noContentResponse();
    }

    private function historyFor(Request $request)
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        return $this->medicalHistory->forPatientRecord($patientRecord);
    }
}
