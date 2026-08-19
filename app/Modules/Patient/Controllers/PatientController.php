<?php

namespace App\Modules\Patient\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Patient\Requests\SearchPatientRequest;
use App\Modules\Patient\Resources\PatientSearchResource;
use App\Modules\Patient\Services\PatientQueryService;
use Illuminate\Http\JsonResponse;

class PatientController extends BaseController
{
    public function __construct(
        private readonly PatientQueryService $queries,
    ) {}

    public function search(SearchPatientRequest $request): JsonResponse
    {
        $patients = $this->queries->searchForReceptionist($request->validated('q'));

        return $this->successResponse(
            PatientSearchResource::collection($patients),
            'Patients retrieved successfully.'
        );
    }
}
