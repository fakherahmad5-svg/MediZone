<?php

namespace App\Modules\Consultations\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Models\Appointment;
use App\Modules\Consultations\Requests\SendMessageRequest;
use App\Modules\Consultations\Resources\ConsultationListResource;
use App\Modules\Consultations\Resources\ConsultationMessageResource;
use App\Modules\Consultations\Resources\ConsultationResource;
use App\Modules\Consultations\Services\ConsultationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends BaseController
{
    public function __construct(private readonly ConsultationService $consultations)
    {
    }

    // GET /consultations — inbox listing across all of the current user's
    // appointments (as doctor or patient), newest activity first.
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->consultations->listForUser($request->user(), paginate_per_page()),
            'Consultations retrieved successfully.',
            fn ($consultation) => (new ConsultationListResource($consultation))->resolve($request)
        );
    }

    // GET /appointments/{appointmentId}/consultation — get-or-create the
    // chat thread for this appointment. There's no separate "start
    // consultation" step; opening the chat for the first time creates it.
    public function show(Request $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);
        $consultation = $this->consultations->resolveForAppointment($appointment, $request->user());

        return $this->successResponse(
            new ConsultationResource($consultation),
            'Consultation retrieved successfully.'
        );
    }

    public function messages(Request $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);
        $consultation = $this->consultations->resolveForAppointment($appointment, $request->user());

        return $this->paginatedResponse(
            $this->consultations->listMessages($consultation, paginate_per_page()),
            'Messages retrieved successfully.',
            fn ($message) => (new ConsultationMessageResource($message))->resolve($request)
        );
    }

    public function sendMessage(SendMessageRequest $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);
        $consultation = $this->consultations->resolveForAppointment($appointment, $request->user());

        $message = $this->consultations->sendMessage(
            $appointment,
            $consultation,
            $request->user(),
            $request->validated('content')
        );

        return $this->createdResponse(
            new ConsultationMessageResource($message->fresh('sender')),
            'Message sent successfully.'
        );
    }

    public function markRead(Request $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);
        $consultation = $this->consultations->resolveForAppointment($appointment, $request->user());

        $updated = $this->consultations->markRead($consultation, $request->user());

        return $this->successResponse(['updated' => $updated], 'Messages marked as read.');
    }

    // ─────────────────────────────────────────────────────────────

    private function resolveAppointment(int $appointmentId): Appointment
    {
        $appointment = Appointment::with(['doctor', 'patient'])->find($appointmentId);

        if (! $appointment) {
            throw new NotFoundException('Appointment not found.');
        }

        return $appointment;
    }
}
