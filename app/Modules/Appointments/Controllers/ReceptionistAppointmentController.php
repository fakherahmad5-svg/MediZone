<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Enums\ConsultationType;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Appointments\Requests\CancelAppointmentRequest;
use App\Modules\Appointments\Requests\CreateWalkInAppointmentRequest;
use App\Modules\Appointments\Requests\ReceptionistBookAppointmentRequest;
use App\Modules\Appointments\Resources\AppointmentResource;
use App\Modules\Appointments\Services\AppointmentBookingService;
use App\Modules\Appointments\Services\AppointmentQueryService;
use App\Modules\Appointments\Services\AppointmentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ReceptionistAppointmentController extends BaseController
{
    public function __construct(
        private readonly AppointmentBookingService $booking,
        private readonly AppointmentStatusService $status,
        private readonly AppointmentQueryService $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'doctor_id', 'date']);

        return $this->paginatedResponse(
            $this->queries->forReceptionist($request->user(), $filters, paginate_per_page()),
            'Appointments retrieved successfully.',
            fn ($appointment) => (new AppointmentResource($appointment))->resolve($request)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForReceptionist($request->user(), $id);

        return $this->successResponse(
            new AppointmentResource($appointment),
            'Appointment retrieved successfully.'
        );
    }

    public function store(ReceptionistBookAppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $appointment = $this->booking->bookOnBehalf(
            $request->user(),
            $data['patient_id'],
            $data['slot_id'],
            ConsultationType::from($data['encounter_type']),
            $data['notes'] ?? null
        );

        $this->ensureSameClinic($request, $appointment->clinic_id);

        return $this->createdResponse(
            new AppointmentResource($appointment),
            'Appointment booked successfully.'
        );
    }

    public function walkIn(CreateWalkInAppointmentRequest $request): JsonResponse
    {
        $clinicId = $this->receptionistClinicId($request);
        $data     = $request->validated();

        $appointment = $this->booking->createWalkIn(
            $request->user(),
            $clinicId,
            $data['patient_id'],
            $data['doctor_id'],
            ConsultationType::from($data['encounter_type']),
            $data['notes'] ?? null
        );

        return $this->createdResponse(
            new AppointmentResource($appointment),
            'Walk-in appointment created successfully.'
        );
    }

    public function checkIn(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForReceptionist($request->user(), $id);

        $updated = $this->status->checkIn($appointment, $request->user());

        return $this->successResponse(
            new AppointmentResource($updated),
            'Patient checked in successfully.'
        );
    }

    public function cancel(CancelAppointmentRequest $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForReceptionist($request->user(), $id);

        $updated = $this->status->cancel($appointment, $request->user(), $request->validated('reason'));

        return $this->successResponse(
            new AppointmentResource($updated),
            'Appointment cancelled.'
        );
    }

    public function noShow(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForReceptionist($request->user(), $id);

        $updated = $this->status->markNoShow($appointment, $request->user());

        return $this->successResponse(
            new AppointmentResource($updated),
            'Appointment marked as no-show.'
        );
    }

    // ─────────────────────────────────────────────────────────────

    private function receptionistClinicId(Request $request): int
    {
        $clinicId = $request->user()->clinicUsers()->value('clinic_id');

        if (! $clinicId) {
            throw new AuthorizationException('Your account is not linked to any clinic.');
        }

        return $clinicId;
    }

    private function ensureSameClinic(Request $request, int $appointmentClinicId): void
    {
        if ($this->receptionistClinicId($request) !== $appointmentClinicId) {
            throw new AuthorizationException('You can only book appointments within your own clinic.');
        }
    }
}
