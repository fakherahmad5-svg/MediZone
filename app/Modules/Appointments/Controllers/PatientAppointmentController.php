<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
use App\Core\Http\Controllers\BaseController;
use App\Models\Appointment;
use App\Modules\Appointments\Requests\BookAppointmentRequest;
use App\Modules\Appointments\Requests\CancelAppointmentRequest;
use App\Modules\Appointments\Requests\RescheduleAppointmentRequest;
use App\Modules\Appointments\Resources\AppointmentResource;
use App\Modules\Appointments\Services\AppointmentBookingService;
use App\Modules\Appointments\Services\AppointmentCancellationService;
use App\Modules\Appointments\Services\AppointmentQueryService;
use App\Modules\Appointments\Services\AppointmentStatusService;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientAppointmentController extends BaseController
{
    public function __construct(
        private readonly AppointmentBookingService $booking,
        private readonly AppointmentStatusService $status,
        private readonly AppointmentQueryService $queries,
        private readonly AppointmentCancellationService $cancellationService,
        private readonly StripePaymentService $stripe,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        $filters = $request->only(['status']);

        return $this->paginatedResponse(
            $this->queries->forPatient(
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
        $appointment = $this->queries->findForPatient(
            $request->user(),
            $id
        );

        $this->authorize('view', $appointment);

        return $this->successResponse(
            new AppointmentResource($appointment),
            'Appointment retrieved successfully.'
        );
    }

    public function store(BookAppointmentRequest $request): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $data = $request->validated();

        $appointment = $this->booking->book(
            $request->user(),
            $data['slot_id'],
            ConsultationType::from($data['encounter_type']),
            PaymentMethod::from($data['payment_method']),
            $data['notes'] ?? null
        );

        return $this->createdResponse(
            new AppointmentResource($appointment),
            'Appointment booked successfully.'
        );
    }

    /**
     * Start a Stripe Checkout Session for an already-booked appointment.
     * Kept as a separate step from store() rather than folded into
     * booking, since the frontend needs the appointment's id/price
     * confirmed before redirecting the patient into Stripe Checkout.
     */
    public function checkout(Request $request, int $id): JsonResponse
    {
        $appointment = $this->queries->findForPatient($request->user(), $id);

        $this->authorize('view', $appointment);

        $result = $this->stripe->createCheckoutSessionForAppointment(
            $appointment,
            config('app.frontend_url') . '/appointments/' . $appointment->id . '/payment-success',
            config('app.frontend_url') . '/appointments/' . $appointment->id . '/payment-cancelled'
        );

        return $this->successResponse(
            [
                'checkout_url' => $result['session']->url,
                'session_id' => $result['session']->id,
            ],
            'Checkout session created successfully.'
        );
    }

    public function cancel(
        CancelAppointmentRequest $request,
        int $id
    ): JsonResponse {
        $appointment = $this->queries->findForPatient(
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
            'Appointment cancelled successfully.'
        );
    }

    public function reschedule(
        RescheduleAppointmentRequest $request,
        int $id
    ): JsonResponse {
        $appointment = $this->queries->findForPatient(
            $request->user(),
            $id
        );

        $this->authorize('reschedule', $appointment);

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