<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\ResolvesPatientRecord;
use App\Models\ChronicCondition;
use App\Modules\Medical\Requests\StoreChronicConditionRequest;
use App\Modules\Medical\Requests\UpdateChronicConditionRequest;
use App\Modules\Medical\Resources\ChronicConditionResource;
use App\Modules\Medical\Services\MedicalHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ChronicConditionController extends BaseController
{
    use ResolvesPatientRecord;

    public function __construct(
        private readonly MedicalHistoryService $medicalHistory
    ) {}

    public function index(Request $request): JsonResponse
    {
        $history = $this->historyFor($request);

        return $this->successResponse(
            ChronicConditionResource::collection($history->chronicConditions),
            'Chronic conditions retrieved successfully.'
        );
    }

    public function store(StoreChronicConditionRequest $request): JsonResponse
    {
        $history = $this->historyFor($request);

        $condition = $this->medicalHistory->addChronicCondition($history, $request->validated());

        return $this->createdResponse(
            new ChronicConditionResource($condition),
            'Chronic condition added successfully.'
        );
    }

    public function update(UpdateChronicConditionRequest $request, int $id): JsonResponse
    {
        $history   = $this->historyFor($request);
        $condition = ChronicCondition::findOrFail($id);

        $updated = $this->medicalHistory->updateChronicCondition($history, $condition, $request->validated());

        return $this->successResponse(
            new ChronicConditionResource($updated),
            'Chronic condition updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $history   = $this->historyFor($request);
        $condition = ChronicCondition::findOrFail($id);

        $this->medicalHistory->deleteChronicCondition($history, $condition);

        return $this->noContentResponse();
    }

    private function historyFor(Request $request)
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        return $this->medicalHistory->forPatientRecord($patientRecord);
    }
}
