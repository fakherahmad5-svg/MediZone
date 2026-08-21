<?php

namespace Tests\Feature\Phase7;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\SlotStatus;
use App\Core\Exceptions\BusinessException;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorProfile;
use App\Models\DoctorTimeSlot;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookOnBehalfCashFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeDoctorWithFee(string $fee): Doctor
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        DoctorProfile::factory()->create([
            'doctor_id' => $doctor->id,
            'consultation_fee' => $fee,
        ]);

        return $doctor->fresh();
    }

    // ─────────────────────────────────────────────────────────
    //  bookOnBehalf() — receptionist books for a patient
    // ─────────────────────────────────────────────────────────

    public function test_book_on_behalf_creates_awaiting_payment_appointment_with_pending_cash_payment(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('100.00');
        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $appointment = app(AppointmentBookingService::class)->bookOnBehalf(
            $receptionistUser,
            $patient->id,
            $slot->id,
            ConsultationType::cases()[0]
        );

        $this->assertSame(AppointmentStatus::AwaitingPayment, $appointment->status);
        $this->assertSame('0.00', $appointment->deposit_amount);
        $this->assertSame('100.00', $appointment->remaining_cash_amount);

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'status' => PaymentStatus::Pending->value,
            'amount' => '100.00',
        ]);
    }

    public function test_book_on_behalf_does_not_lock_the_slot(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('100.00');
        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        app(AppointmentBookingService::class)->bookOnBehalf(
            $receptionistUser,
            $patient->id,
            $slot->id,
            ConsultationType::cases()[0]
        );

        $this->assertDatabaseHas('doctor_time_slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Available->value,
        ]);
    }

    public function test_confirm_cash_payment_on_book_on_behalf_marks_paid_schedules_and_locks_slot(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('100.00');
        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $service = app(AppointmentBookingService::class);

        $appointment = $service->bookOnBehalf(
            $receptionistUser,
            $patient->id,
            $slot->id,
            ConsultationType::cases()[0]
        );

        $confirmed = $service->confirmCashPayment($appointment, $receptionistUser);

        $this->assertSame(AppointmentStatus::Scheduled, $confirmed->status);

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'status' => PaymentStatus::Paid->value,
        ]);

        $this->assertDatabaseHas('doctor_time_slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Booked->value,
        ]);
    }

    public function test_confirm_cash_payment_rejects_when_no_pending_payment_exists(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('100.00');
        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $service = app(AppointmentBookingService::class);

        $appointment = $service->bookOnBehalf(
            $receptionistUser,
            $patient->id,
            $slot->id,
            ConsultationType::cases()[0]
        );

        // Simulate the pending payment having disappeared / already resolved.
        Payment::where('appointment_id', $appointment->id)->delete();

        $this->expectException(BusinessException::class);

        $service->confirmCashPayment($appointment, $receptionistUser);
    }

    public function test_confirm_cash_payment_leaves_appointment_and_slot_untouched_when_no_pending_payment(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('100.00');
        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $service = app(AppointmentBookingService::class);

        $appointment = $service->bookOnBehalf(
            $receptionistUser,
            $patient->id,
            $slot->id,
            ConsultationType::cases()[0]
        );

        Payment::where('appointment_id', $appointment->id)->delete();

        try {
            $service->confirmCashPayment($appointment, $receptionistUser);
        } catch (BusinessException $e) {
            // expected — asserted separately above, ignored here on purpose
        }

        $this->assertSame(
            AppointmentStatus::AwaitingPayment,
            $appointment->fresh()->status
        );

        $this->assertDatabaseHas('doctor_time_slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Available->value,
        ]);
    }

    public function test_confirm_cash_payment_rejects_when_appointment_not_awaiting_payment(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('100.00');
        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $service = app(AppointmentBookingService::class);

        $appointment = $service->bookOnBehalf(
            $receptionistUser,
            $patient->id,
            $slot->id,
            ConsultationType::cases()[0]
        );

        $service->confirmCashPayment($appointment, $receptionistUser);

        $this->expectException(BusinessException::class);

        // Second confirmation attempt on an already-Scheduled appointment.
        $service->confirmCashPayment($appointment->fresh(), $receptionistUser);
    }

    // ─────────────────────────────────────────────────────────
    //  createWalkIn() — patient physically on site, two-phase cash flow
    // ─────────────────────────────────────────────────────────

    public function test_walk_in_creates_awaiting_payment_appointment_with_pending_cash_payment(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('150.00');
        $clinic = Clinic::factory()->create();

        $appointment = app(AppointmentBookingService::class)->createWalkIn(
            $receptionistUser,
            $clinic->id,
            $patient->id,
            $doctor->id,
            ConsultationType::cases()[0]
        );

        // Walk-in is NOT CheckedIn immediately anymore — it waits for
        // the receptionist to confirm the cash was actually received.
        $this->assertSame(AppointmentStatus::AwaitingPayment, $appointment->status);
        $this->assertNull($appointment->slot_id);
        $this->assertSame('0.00', $appointment->deposit_amount);
        $this->assertSame('150.00', $appointment->remaining_cash_amount);

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'status' => PaymentStatus::Pending->value,
            'amount' => '150.00',
        ]);
    }

    public function test_confirm_cash_payment_on_walk_in_marks_checked_in_without_touching_any_slot(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('150.00');
        $clinic = Clinic::factory()->create();

        $service = app(AppointmentBookingService::class);

        $appointment = $service->createWalkIn(
            $receptionistUser,
            $clinic->id,
            $patient->id,
            $doctor->id,
            ConsultationType::cases()[0]
        );

        $confirmed = $service->confirmCashPayment($appointment, $receptionistUser);

        // Walk-in has no slot_id — confirmation must NOT try to lock one,
        // and the target status is CheckedIn, not Scheduled.
        $this->assertSame(AppointmentStatus::CheckedIn, $confirmed->status);
        $this->assertNull($confirmed->slot_id);

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'status' => PaymentStatus::Paid->value,
        ]);
    }

    public function test_confirm_cash_payment_on_walk_in_rejects_when_no_pending_payment_exists(): void
    {
        $receptionistUser = User::factory()->create();
        $patient = Patient::factory()->create();
        $doctor = $this->makeDoctorWithFee('150.00');
        $clinic = Clinic::factory()->create();

        $service = app(AppointmentBookingService::class);

        $appointment = $service->createWalkIn(
            $receptionistUser,
            $clinic->id,
            $patient->id,
            $doctor->id,
            ConsultationType::cases()[0]
        );

        Payment::where('appointment_id', $appointment->id)->delete();

        $this->expectException(BusinessException::class);

        $service->confirmCashPayment($appointment, $receptionistUser);
    }
}