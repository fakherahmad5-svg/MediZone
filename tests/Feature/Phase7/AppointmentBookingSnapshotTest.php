<?php

namespace Tests\Feature\Phase7;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\CashDepositType;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\SlotStatus;
use App\Models\Doctor;
use App\Models\DoctorProfile;
use App\Models\DoctorSetting;
use App\Models\DoctorTimeSlot;
use App\Models\Patient;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentBookingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function makePatientUser(): User
    {
        $user = User::factory()->create();
        Patient::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function makeDoctorWithProfile(string $fee, string $commissionPercentage): Doctor
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'commission_percentage' => $commissionPercentage,
        ]);

        DoctorProfile::factory()->create([
            'doctor_id' => $doctor->id,
            'consultation_fee' => $fee,
        ]);

        return $doctor->fresh();
    }

    public function test_online_booking_sets_awaiting_payment_and_full_price_deposit_snapshot(): void
    {
        $patientUser = $this->makePatientUser();
        $doctor = $this->makeDoctorWithProfile('100.00', '10.00');

        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $appointment = app(AppointmentBookingService::class)->book(
            $patientUser,
            $slot->id,
            ConsultationType::cases()[0],
            PaymentMethod::Card
        );

        $this->assertSame(AppointmentStatus::AwaitingPayment, $appointment->status);
        $this->assertSame('100.00', $appointment->deposit_amount);
        $this->assertSame('0.00', $appointment->remaining_cash_amount);
        $this->assertNull($appointment->deposit_percentage);
        $this->assertSame('10.00', $appointment->commission_percentage);
        $this->assertSame('10.00', $appointment->commission_amount);
    }

    public function test_cash_booking_sets_percentage_deposit_snapshot(): void
    {
        $patientUser = $this->makePatientUser();
        $doctor = $this->makeDoctorWithProfile('100.00', '10.00');

        DoctorSetting::create([
            'doctor_id' => $doctor->id,
            'cash_deposit_type' => CashDepositType::Percentage,
            'cash_deposit_value' => '20.00',
        ]);

        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $appointment = app(AppointmentBookingService::class)->book(
            $patientUser,
            $slot->id,
            ConsultationType::cases()[0],
            PaymentMethod::Cash
        );

        $this->assertSame('20.00', $appointment->deposit_amount);
        $this->assertSame('80.00', $appointment->remaining_cash_amount);
        $this->assertSame('20.00', $appointment->deposit_percentage);
        $this->assertSame(CashDepositType::Percentage, $appointment->deposit_type);
        $this->assertSame('10.00', $appointment->commission_amount);
    }
public function test_slot_remains_available_while_awaiting_payment(): void
{
    $patientUser = $this->makePatientUser();
    $doctor = $this->makeDoctorWithProfile('100.00', '10.00');

    $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

    $appointment = app(AppointmentBookingService::class)->book(
        $patientUser,
        $slot->id,
        ConsultationType::cases()[0],
        PaymentMethod::Card
    );

    $this->assertSame(
        AppointmentStatus::AwaitingPayment,
        $appointment->status
    );

    $this->assertDatabaseHas('doctor_time_slots', [
        'id' => $slot->id,
        'status' => SlotStatus::Available->value,
    ]);
}


    public function test_changing_doctor_setting_after_booking_does_not_affect_existing_appointment(): void
    {
        $patientUser = $this->makePatientUser();
        $doctor = $this->makeDoctorWithProfile('100.00', '10.00');

        $setting = DoctorSetting::create([
            'doctor_id' => $doctor->id,
            'cash_deposit_type' => CashDepositType::Percentage,
            'cash_deposit_value' => '20.00',
        ]);

        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $appointment = app(AppointmentBookingService::class)->book(
            $patientUser,
            $slot->id,
            ConsultationType::cases()[0],
            PaymentMethod::Cash
        );

        // Admin changes the deposit percentage after booking.
        $setting->update(['cash_deposit_value' => '15.00']);

        $appointment->refresh();

        $this->assertSame('20.00', $appointment->deposit_percentage);
        $this->assertSame('20.00', $appointment->deposit_amount);
    }

    public function test_changing_doctor_commission_after_booking_does_not_affect_existing_appointment(): void
    {
        $patientUser = $this->makePatientUser();
        $doctor = $this->makeDoctorWithProfile('100.00', '10.00');

        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $appointment = app(AppointmentBookingService::class)->book(
            $patientUser,
            $slot->id,
            ConsultationType::cases()[0],
            PaymentMethod::Card
        );

        $doctor->update(['commission_percentage' => '15.00']);

        $appointment->refresh();

        $this->assertSame('10.00', $appointment->commission_percentage);
        $this->assertSame('10.00', $appointment->commission_amount);
    }
}