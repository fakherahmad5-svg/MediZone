<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Enums\AppointmentPaymentMethod;
use App\Core\Enums\ConsultationType;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Appointments\Requests\BookAppointmentRequest;
use App\Modules\Appointments\Requests\CancelAppointmentRequest;
use App\Modules\Appointments\Requests\RescheduleAppointmentRequest;
use App\Modules\Appointments\Resources\AppointmentResource;
use App\Modules\Appointments\Services\AppointmentBookingService;
use App\Modules\Appointments\Services\AppointmentQueryService;
use App\Modules\Appointments\Services\AppointmentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class PatientAppointmentController extends BaseController
{
    public function __construct(
        private readonly AppointmentBookingService $booking,
        private readonly AppointmentStatusService $status,
        private readonly AppointmentQueryService $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);

        return $this->paginatedResponse(
            $this->queries->forPatient($request->user(), $filters, paginate_per_page()),
            'Appointments retrieved successfully.',
            fn ($appointment) => (new AppointmentResource($appointment))->resolve($request)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForPatient($request->user(), $id);

        return $this->successResponse(
            new AppointmentResource($appointment),
            'Appointment retrieved successfully.'
        );
    }

    public function store(BookAppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $appointment = $this->booking->book(
            $request->user(),
            $data['slot_id'],
            ConsultationType::from($data['encounter_type']),
            AppointmentPaymentMethod::from($data['payment_method']),
            $data['notes'] ?? null
        );

        return $this->createdResponse(
            new AppointmentResource($appointment),
            'Appointment booked successfully.'
        );
    }

    public function cancel(CancelAppointmentRequest $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForPatient($request->user(), $id);

        $updated = $this->status->cancel($appointment, $request->user(), $request->validated('reason'));

        return $this->successResponse(
            new AppointmentResource($updated),
            'Appointment cancelled successfully.'
        );
    }

    public function reschedule(RescheduleAppointmentRequest $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForPatient($request->user(), $id);

        $updated = $this->booking->reschedule(
            $request->user(),
            $appointment,
            $request->validated('new_slot_id')
        );

        return $this->successResponse(
            new AppointmentResource($updated),
            'Appointment rescheduled successfully.'
        );
    }
}
