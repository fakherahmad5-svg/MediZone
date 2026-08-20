<?php

namespace Tests\Feature\Phase7;

use App\Core\Enums\CashDepositType;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
use App\Models\Doctor;
use App\Models\DoctorSetting;
use App\Models\DoctorTimeSlot;
use App\Models\Patient;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentBookingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_stores_the_selected_payment_method(): void
    {
        $patientUser = User::factory()->create();
        Patient::factory()->create(['user_id' => $patientUser->id]);
        $patientUser->refresh();

        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $slot = DoctorTimeSlot::factory()->create(['doctor_id' => $doctor->id]);

        $appointment = app(AppointmentBookingService::class)->book(
            $patientUser,
            $slot->id,
            ConsultationType::cases()[0],
            PaymentMethod::Card
        );

        $this->assertSame(PaymentMethod::Card, $appointment->payment_method);
    }
    

    public function test_booking_stores_cash_payment_method(): void
    {
        $patientUser = User::factory()->create();
        Patient::factory()->create(['user_id' => $patientUser->id]);
        $patientUser->refresh();

        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        // NEW — required now that Cash bookings compute a real deposit
        // snapshot at booking time; without this the booking throws
        // DomainValidationException.
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

        $this->assertSame(PaymentMethod::Cash, $appointment->payment_method);
    }
}