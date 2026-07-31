<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\ResolvesPatientRecord;
use App\Models\Medication;
use App\Modules\Medical\Requests\DrugSearchRequest;
use App\Modules\Medical\Requests\StoreMedicationRequest;
use App\Modules\Medical\Requests\StopMedicationRequest;
use App\Modules\Medical\Requests\UpdateMedicationRequest;
use App\Modules\Medical\Resources\DrugResource;
use App\Modules\Medical\Resources\MedicationResource;
use App\Modules\Medical\Services\MedicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class MedicationController extends BaseController
{
    use ResolvesPatientRecord;

    public function __construct(
        private readonly MedicationService $medications
    ) {}

    public function index(Request $request): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        return $this->successResponse(
            MedicationResource::collection($this->medications->listForPatientRecord($patientRecord)),
            'Medications retrieved successfully.'
        );
    }

    public function store(StoreMedicationRequest $request): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        $medication = $this->medications->addSelfReported(
            $patientRecord,
            $request->user(),
            $request->validated()
        );

        return $this->createdResponse(
            new MedicationResource($medication->load('drug')),
            'Medication added successfully.'
        );
    }

    public function update(UpdateMedicationRequest $request, int $id): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());
        $medication    = Medication::findOrFail($id);

        $updated = $this->medications->update($patientRecord, $medication, $request->validated());

        return $this->successResponse(
            new MedicationResource($updated),
            'Medication updated successfully.'
        );
    }

    public function stop(StopMedicationRequest $request, int $id): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());
        $medication    = Medication::findOrFail($id);

        $updated = $this->medications->stop($patientRecord, $medication, $request->validated('reason'));

        return $this->successResponse(
            new MedicationResource($updated),
            'Medication marked as stopped.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());
        $medication    = Medication::findOrFail($id);

        $this->medications->delete($patientRecord, $medication);

        return $this->noContentResponse();
    }

    public function searchDrugs(DrugSearchRequest $request): JsonResponse
    {
        return $this->successResponse(
            DrugResource::collection($this->medications->searchDrugs($request->validated('q'))),
            'Drugs retrieved successfully.'
        );
    }
}
