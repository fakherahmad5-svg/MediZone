<?php

namespace App\Modules\DoctorEngagement\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\DoctorEngagement\Requests\ReportFeedbackRequest;
use App\Modules\DoctorEngagement\Requests\SubmitReportRequest;
use App\Modules\DoctorEngagement\Resources\DoctorReportResource;
use App\Modules\DoctorEngagement\Services\DoctorReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class PatientReportController extends BaseController
{
    public function __construct(private readonly DoctorReportService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $reports = $this->reports->listForPatient($request->user(), $request->integer('per_page', 15));

        return $this->paginatedResponse(
            $reports,
            'Your reports retrieved successfully.',
            fn ($r) => (new DoctorReportResource($r))->resolve($request)
        );
    }

    public function store(SubmitReportRequest $request, int $doctorId): JsonResponse
    {
        $report = $this->reports->submit(
            $request->user(),
            $doctorId,
            $request->validated('category'),
            $request->validated('description'),
            $request->validated('encounter_id')
        );

        return $this->createdResponse(new DoctorReportResource($report), 'Report submitted successfully.');
    }

    public function feedback(ReportFeedbackRequest $request, int $reportId): JsonResponse
    {
        $report = $this->reports->addPatientFeedback(
            $request->user(),
            $reportId,
            $request->validated('feedback')
        );

        return $this->successResponse(new DoctorReportResource($report), 'Feedback submitted successfully.');
    }
}
