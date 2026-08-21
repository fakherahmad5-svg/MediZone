<?php

namespace App\Modules\Consultations\Services;

use App\Core\Enums\ConsultationStatus;
use App\Core\Enums\MessageSenderRole;
use App\Core\Enums\MessageType;
use App\Core\Enums\NotificationType;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Services\BaseService;
use App\Core\Services\NotificationService;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsultationService extends BaseService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function resolveForAppointment(Appointment $appointment, User $user): Consultation
    {
        $this->assertParticipant($appointment, $user);

        return Consultation::firstOrCreate(
            ['appointment_id' => $appointment->id],
            ['status' => ConsultationStatus::Waiting->value]
        );
    }

    // Inbox listing — every consultation thread the current user is a
    // participant in, across all of their appointments. Doctor and patient
    // accounts are mutually exclusive in practice, so whichever profile
    // exists on the user decides which side of `appointments` to filter on.
    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $doctor = $user->doctor;
        $patient = $user->patient;

        if (! $doctor && ! $patient) {
            throw new AuthorizationException('This account has no doctor or patient profile.');
        }

        $query = Consultation::query()
            ->with([
                'appointment.doctor.user:id,first_name,last_name',
                'appointment.patient.user:id,first_name,last_name',
                'latestMessage',
            ])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)->where('is_read', false);
            }]);

        if ($doctor) {
            $query->whereHas('appointment', fn ($q) => $q->where('doctor_id', $doctor->id));
        } else {
            $query->whereHas('appointment', fn ($q) => $q->where('patient_id', $patient->id));
        }

        return $query->latest('updated_at')->paginate($perPage);
    }

    public function listMessages(Consultation $consultation, int $perPage = 30): LengthAwarePaginator
    {
        return ConsultationMessage::where('consultation_id', $consultation->id)
            ->with('sender:id,first_name,last_name')
            ->oldest('id')
            ->paginate($perPage);
    }

    public function sendMessage(Appointment $appointment, Consultation $consultation, User $sender, string $content): ConsultationMessage
    {
        $senderRole = $this->roleFor($appointment, $sender);

        $message = $this->transaction(function () use ($consultation, $sender, $senderRole, $content) {
            $message = ConsultationMessage::create([
                'consultation_id' => $consultation->id,
                'sender_id'       => $sender->id,
                'sender_role'     => $senderRole->value,
                'message_type'    => MessageType::Text->value,
                'content'         => $content,
            ]);

            if ($consultation->status === ConsultationStatus::Waiting->value) {
                $consultation->update([
                    'status'     => ConsultationStatus::Active->value,
                    'started_at' => $consultation->started_at ?? now(),
                ]);
            }

            return $message;
        });

        $this->notifyOtherParticipant($appointment, $sender, $senderRole);

        return $message;
    }

    public function markRead(Consultation $consultation, User $reader): int
    {
        return ConsultationMessage::where('consultation_id', $consultation->id)
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    // ─────────────────────────────────────────────────────────────

    public function assertParticipant(Appointment $appointment, User $user): void
    {
        $appointment->loadMissing(['doctor', 'patient']);

        $isDoctor  = $appointment->doctor && $appointment->doctor->user_id === $user->id;
        $isPatient = $appointment->patient && $appointment->patient->user_id === $user->id;

        if (! $isDoctor && ! $isPatient) {
            throw new AuthorizationException('You are not a participant in this consultation.');
        }
    }

    private function roleFor(Appointment $appointment, User $user): MessageSenderRole
    {
        return $appointment->doctor->user_id === $user->id
            ? MessageSenderRole::Doctor
            : MessageSenderRole::Patient;
    }

    // Fires a push (via NotificationService/FCM) to whichever side of the
    // conversation didn't just send this message. Silently no-ops if that
    // side has no linked user (shouldn't happen) or no registered device —
    // NotificationService already degrades to an in-app-only record then.
    private function notifyOtherParticipant(Appointment $appointment, User $sender, MessageSenderRole $senderRole): void
    {
        $appointment->loadMissing(['doctor.user', 'patient.user']);

        $recipient = $senderRole === MessageSenderRole::Doctor
            ? $appointment->patient?->user
            : $appointment->doctor?->user;

        if (! $recipient) {
            return;
        }

        $this->notifications->notify($recipient, NotificationType::ConsultationMessageReceived, [
            'sender_name' => $sender->full_name,
        ]);
    }
}
