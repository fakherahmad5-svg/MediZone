<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\ResolvesPatientRecord;
use App\Models\Surgery;
use App\Modules\Medical\Requests\StoreSurgeryRequest;
use App\Modules\Medical\Requests\UpdateSurgeryRequest;
use App\Modules\Medical\Resources\SurgeryResource;
use App\Modules\Medical\Services\MedicalHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurgeryController extends BaseController
{
    use ResolvesPatientRecord;

    public function __construct(
        private readonly MedicalHistoryService $medicalHistory
    ) {}

    public function index(Request $request): JsonResponse
    {
        $history = $this->historyFor($request);

        return $this->successResponse(
            SurgeryResource::collection($history->surgeries),
            'Surgeries retrieved successfully.'
        );
    }

    public function store(StoreSurgeryRequest $request): JsonResponse
    {
        $history = $this->historyFor($request);

        $surgery = $this->medicalHistory->addSurgery($history, $request->validated());

        return $this->createdResponse(
            new SurgeryResource($surgery),
            'Surgery added successfully.'
        );
    }

    public function update(UpdateSurgeryRequest $request, int $id): JsonResponse
    {
        $history = $this->historyFor($request);
        $surgery = Surgery::findOrFail($id);

        $updated = $this->medicalHistory->updateSurgery($history, $surgery, $request->validated());

        return $this->successResponse(
            new SurgeryResource($updated),
            'Surgery updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $history = $this->historyFor($request);
        $surgery = Surgery::findOrFail($id);

        $this->medicalHistory->deleteSurgery($history, $surgery);

        return $this->noContentResponse();
    }

    private function historyFor(Request $request)
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        return $this->medicalHistory->forPatientRecord($patientRecord);
    }
}
