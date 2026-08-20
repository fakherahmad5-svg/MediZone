<?php

namespace App\Jobs;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\NotificationType;
use App\Core\Services\NotificationService;
use App\Models\Appointment;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class SendAppointmentRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<string, int> */
    private const WINDOWS = [
        '24h' => 24,
        '1h'  => 1,
    ];

    public function handle(NotificationService $notifications): void
    {
        foreach (self::WINDOWS as $label => $hoursBefore) {
            $this->remindForWindow($notifications, $label, $hoursBefore);
        }
    }

    private function remindForWindow(NotificationService $notifications, string $label, int $hoursBefore): void
    {
        $target = now()->addHours($hoursBefore);

        $appointments = Appointment::where('status', AppointmentStatus::Scheduled->value)
            ->whereHas('slot', function ($q) use ($target) {
                $q->whereBetween('starts_at', [
                    $target->copy()->subMinutes(7),
                    $target->copy()->addMinutes(7),
                ]);
            })
            ->with(['patient.user', 'doctor.user', 'slot'])
            ->get();

        foreach ($appointments as $appointment) {
            if (! $appointment->patient?->user || ! $appointment->slot) {
                continue;
            }

            $alreadySent = Notification::where('user_id', $appointment->patient->user_id)
                ->where('type', NotificationType::AppointmentReminder->value)
                ->whereJsonContains('data->appointment_id', $appointment->id)
                ->whereJsonContains('data->window', $label)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $notifications->notify($appointment->patient->user, NotificationType::AppointmentReminder, [
                'doctor_name'      => $this->doctorName($appointment),
                'appointment_time' => $appointment->slot->starts_at->format('H:i'),
                'appointment_id'   => $appointment->id,
                'window'           => $label,
            ]);
        }
    }

    private function doctorName(Appointment $appointment): string
    {
        $user = $appointment->doctor?->user;

        return $user ? trim("{$user->first_name} {$user->last_name}") : '';
    }
}
