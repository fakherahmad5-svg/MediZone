<?php

namespace Tests\Feature\Phase7;

use App\Core\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentNoShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_no_show_increments_patient_count_but_does_not_block_cash(): void
    {
        $patient = Patient::factory()->create([
            'no_show_count' => 0,
            'cash_payment_blocked' => false,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'status' => AppointmentStatus::Scheduled,
        ]);

        $doctorUser = User::factory()->create();

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        $appointment->update([
            'doctor_id' => $doctor->id,
        ]);

        app(AppointmentStatusService::class)->markNoShow(
            $appointment->fresh(),
            $doctorUser
        );

        $patient->refresh();
        $appointment->refresh();

        $this->assertSame(1, $patient->no_show_count);
        $this->assertFalse($patient->cash_payment_blocked);
        $this->assertSame(
            AppointmentStatus::NoShow,
            $appointment->status
        );
    }

    public function test_second_no_show_blocks_cash_payment(): void
    {
        $patient = Patient::factory()->create([
            'no_show_count' => 1,
            'cash_payment_blocked' => false,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'status' => AppointmentStatus::Scheduled,
        ]);

        $doctorUser = User::factory()->create();

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        $appointment->update([
            'doctor_id' => $doctor->id,
        ]);

        app(AppointmentStatusService::class)->markNoShow(
            $appointment->fresh(),
            $doctorUser
        );

        $patient->refresh();
        $appointment->refresh();

        $this->assertSame(2, $patient->no_show_count);
        $this->assertTrue($patient->cash_payment_blocked);
        $this->assertSame(
            AppointmentStatus::NoShow,
            $appointment->status
        );
    }
}
