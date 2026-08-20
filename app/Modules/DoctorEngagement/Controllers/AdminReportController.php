<?php

namespace App\Modules\DoctorEngagement\Controllers;

use App\Core\Enums\ReportStatus;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Models\DoctorReport;
use App\Modules\DoctorEngagement\Requests\ResolveReportRequest;
use App\Modules\DoctorEngagement\Resources\DoctorReportResource;
use App\Modules\DoctorEngagement\Services\DoctorReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AdminReportController extends BaseController
{
    public function __construct(private readonly DoctorReportService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'category', 'doctor_id']);

        $reports = $this->reports->listForAdmin($filters, $request->integer('per_page', 20));

        return $this->paginatedResponse(
            $reports,
            'Reports retrieved successfully.',
            fn ($r) => (new DoctorReportResource($r))->resolve($request)
        );
    }

    public function markUnderReview(Request $request, int $reportId): JsonResponse
    {
        $report = $this->resolveReport($reportId);

        $report = $this->reports->markUnderReview($request->user(), $report);

        return $this->successResponse(new DoctorReportResource($report), 'Report marked as under review.');
    }

    public function resolve(ResolveReportRequest $request, int $reportId): JsonResponse
    {
        $report = $this->resolveReport($reportId);

        $report = $this->reports->resolve(
            $request->user(),
            $report,
            ReportStatus::from($request->validated('status')),
            $request->validated('admin_action'),
            $request->validated('admin_notes')
        );

        return $this->successResponse(new DoctorReportResource($report), 'Report resolved successfully.');
    }

    private function resolveReport(int $reportId): DoctorReport
    {
        $report = DoctorReport::find($reportId);

        if (! $report) {
            throw new NotFoundException('Report not found.');
        }

        return $report;
    }
}
