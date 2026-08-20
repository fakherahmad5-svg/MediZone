<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Appointments\Requests\CancelAppointmentRequest;
use App\Modules\Appointments\Resources\AppointmentResource;
use App\Modules\Appointments\Services\AppointmentCancellationService;
use App\Modules\Appointments\Services\AppointmentQueryService;
use App\Modules\Appointments\Services\AppointmentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DoctorAppointmentController
 *
 * Routes [auth:sanctum, role:doctor] — UC-D04 + دورة حياة الموعد:
 * GET  /doctor/appointments               → index()
 * GET  /doctor/appointments/{id}          → show()
 * POST /doctor/appointments/{id}/start    → start()
 * POST /doctor/appointments/{id}/complete → complete()
 * POST /doctor/appointments/{id}/cancel   → cancel()
 * POST /doctor/appointments/{id}/no-show  → noShow()
 */
class DoctorAppointmentController extends BaseController
{
    public function __construct(
        private readonly AppointmentStatusService $status,
        private readonly AppointmentQueryService $queries,
        private readonly AppointmentCancellationService $cancellationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'date']);

        return $this->paginatedResponse(
            $this->queries->forDoctor(
                $request->user(),
                $filters,
                paginate_per_page()
            ),
            'Appointments retrieved successfully.',
            fn ($appointment) => (new AppointmentResource($appointment))
                ->resolve($request)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForDoctor(
            $request->user(),
            $id
        );

        $this->authorize('view', $appointment);

        return $this->successResponse(
            new AppointmentResource($appointment),
            'Appointment retrieved successfully.'
        );
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForDoctor(
            $request->user(),
            $id
        );

        $this->authorize('start', $appointment);

        $updated = $this->status->startConsultation(
            $appointment,
            $request->user()
        );

        return $this->successResponse(
            new AppointmentResource($updated),
            'Consultation started.'
        );
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForDoctor(
            $request->user(),
            $id
        );

        $this->authorize('complete', $appointment);

        $updated = $this->status->complete(
            $appointment,
            $request->user()
        );

        return $this->successResponse(
            new AppointmentResource($updated),
            'Appointment marked as completed.'
        );
    }

    public function cancel(
        CancelAppointmentRequest $request,
        int $id
    ): JsonResponse {
        $appointment = $this->queries->findForDoctor(
            $request->user(),
            $id
        );

        $this->authorize('cancel', $appointment);

        $this->cancellationService->cancel(
            $appointment,
            $request->validated('reason'),
            $request->user()
        );

        return $this->successResponse(
            new AppointmentResource($appointment->fresh()),
            'Appointment cancelled.'
        );
    }

    public function noShow(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForDoctor(
            $request->user(),
            $id
        );

        $this->authorize('noShow', $appointment);

        $updated = $this->status->markNoShow(
            $appointment,
            $request->user()
        );
        return $this->successResponse(
            new AppointmentResource($updated),
            'Appointment marked as no-show.'
        );
    }
}