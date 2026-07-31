<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\ResolvesPatientRecord;
use App\Modules\Medical\Resources\MedicalRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class MedicalRecordController extends BaseController
{
    use ResolvesPatientRecord;

    public function me(Request $request): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        $patientRecord->load([
            'medicalHistory.allergies',
            'medicalHistory.chronicConditions',
            'medicalHistory.surgeries',
            'medicalHistory.familyHistories',
            'medications.drug',
            'attachments',
        ]);

        return $this->successResponse(
            new MedicalRecordResource($patientRecord),
            'Medical record retrieved successfully.'
        );
    }
}
