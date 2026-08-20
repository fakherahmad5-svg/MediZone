<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Enums\ConsultationType;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Models\DoctorTimeSlot;
use App\Models\Appointment;
use App\Models\User;
use App\Modules\Appointments\Requests\CancelAppointmentRequest;
use App\Modules\Appointments\Requests\CreateWalkInAppointmentRequest;
use App\Modules\Appointments\Requests\ReceptionistBookAppointmentRequest;
use App\Modules\Appointments\Resources\AppointmentResource;
use App\Modules\Appointments\Services\AppointmentBookingService;
use App\Modules\Appointments\Services\AppointmentCancellationService;
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
        private readonly AppointmentCancellationService $cancellationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        $filters = $request->only([
            'status',
            'doctor_id',
            'date',
        ]);

        return $this->paginatedResponse(
            $this->queries->forReceptionist(
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
        $appointment = $this->queries->findForReceptionist(
            $request->user(),
            $id
        );

        $this->authorize('view', $appointment);

        return $this->successResponse(
            new AppointmentResource($appointment),
            'Appointment retrieved successfully.'
        );
    }

public function store(ReceptionistBookAppointmentRequest $request): JsonResponse
{
    $this->authorize('create', Appointment::class);

    $clinicId = $this->receptionistClinicId($request);

    $data = $request->validated();

    // تحقق الملكية قبل ما نلمس الداتابيز — مش بعدها.
    $slot = DoctorTimeSlot::find($data['slot_id']);

    if (! $slot || $slot->clinic_id !== $clinicId) {
        throw new AuthorizationException('You can only book appointments within your own clinic.');
    }

    $appointment = $this->booking->bookOnBehalf(
        $request->user(),
        $data['patient_id'],
        $data['slot_id'],
        ConsultationType::from($data['encounter_type']),
        $data['notes'] ?? null
    );

    return $this->createdResponse(
        new AppointmentResource($appointment),
        'Appointment booked successfully.'
    );
}
public function findForReceptionist(User $receptionistUser, int $appointmentId): Appointment
{
    $clinicId = $receptionistUser->clinicUsers()->value('clinic_id');

    if (! $clinicId) {
        throw new AuthorizationException('Your account is not linked to any clinic.');
    }

    $appointment = Appointment::where('id', $appointmentId)
        ->where('clinic_id', $clinicId)
        ->with(['doctor.user:id,first_name,last_name', 'patient.user:id,first_name,last_name', 'slot'])
        ->first();

    if (! $appointment) {
        throw new NotFoundException('Appointment not found.');
    }

    return $appointment;
}
    public function walkIn(
        CreateWalkInAppointmentRequest $request
    ): JsonResponse {
        $this->authorize('create', Appointment::class);

        $clinicId = $this->receptionistClinicId($request);

        $data = $request->validated();

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

    public function checkIn(
        Request $request,
        int $id
    ): JsonResponse {
        $appointment = $this->queries->findForReceptionist(
            $request->user(),
            $id
        );

        $this->authorize('checkIn', $appointment);

        $updated = $this->status->checkIn(
            $appointment,
            $request->user()
        );
        return $this->successResponse(
            new AppointmentResource($updated),
            'Patient checked in successfully.'
        );
    }

    public function cancel(
        CancelAppointmentRequest $request,
        int $id
    ): JsonResponse {
        $appointment = $this->queries->findForReceptionist(
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

    public function noShow(
        Request $request,
        int $id
    ): JsonResponse {
        $appointment = $this->queries->findForReceptionist(
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

    private function receptionistClinicId(Request $request): int
    {
        $clinicId = $request->user()
            ->clinicUsers()
            ->value('clinic_id');

        if (! $clinicId) {
            throw new AuthorizationException(
                'Your account is not linked to any clinic.'
            );
        }

        return $clinicId;
    }
}