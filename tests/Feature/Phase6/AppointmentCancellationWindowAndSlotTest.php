<?php

namespace Tests\Feature\Phase6;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\RefundStatus;
use App\Core\Enums\SlotStatus;
use App\Core\Exceptions\AppointmentMissingSlotException;
use App\Core\Exceptions\PatientCancellationWindowExpiredException;
use App\Core\Exceptions\StripeRefundFailedException;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicUser;
use App\Models\DoctorTimeSlot;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentCancellationService;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Refund as StripeRefund;
use Tests\TestCase;

class AppointmentCancellationWindowAndSlotTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────
    // 2-hour patient window
    // ─────────────────────────────────────────────────────────

    public function test_patient_cannot_cancel_within_two_hours_of_appointment(): void
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create();
        $user->setRelation('patient', $patient);

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addHour())
            ->create([
                'patient_id' => $patient->id,
                'status' => AppointmentStatus::Scheduled,
                'price' => '100.00',
            ]);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
            'refunded_at' => null,
        ]);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldNotReceive('refundDestinationCharge');
        $this->app->instance(StripePaymentService::class, $mock);

        $service = app(AppointmentCancellationService::class);

        $this->expectException(PatientCancellationWindowExpiredException::class);

        try {
            $service->cancel($appointment, 'Too close to appointment', $user);
        } finally {
            $appointment->refresh();
            $payment->refresh();

            $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
            $this->assertSame(PaymentStatus::Paid, $payment->status);
            $this->assertSame('0.00', $payment->refunded_amount);
        }
    }

    public function test_patient_can_cancel_exactly_two_hours_before_appointment(): void
    {
        // Freeze to a whole-second boundary before computing the target time.
        // now() still carries microseconds even when frozen via travelTo();
        // if starts_at is computed from that and then persisted to a
        // datetime column that truncates fractional seconds, the stored
        // value ends up a fraction of a second EARLIER than "frozen now + 2h",
        // pushing hoursRemaining just under 2.0 and wrongly tripping the
        // < 2 guard. Rounding down to the second first eliminates that drift.
        $this->travelTo(now()->startOfSecond());

        $patient = Patient::factory()->create();
        $user = User::factory()->create();
        $user->setRelation('patient', $patient);

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->copy()->addHours(2))
            ->create([
                'patient_id' => $patient->id,
                'status' => AppointmentStatus::Scheduled,
                'price' => '100.00',
            ]);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        // At exactly 2 hours out, this falls in the <=24h tier => 6% fee / 94% refund.
        $stripeRefund = StripeRefund::constructFrom([
            'id' => 're_exact_2h',
            'amount' => 9400,
            'status' => 'succeeded',
        ]);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->with($appointment->latestPayment, '94.00')
            ->andReturn($stripeRefund);
        $this->app->instance(StripePaymentService::class, $mock);

        $refund = app(AppointmentCancellationService::class)->cancel($appointment, 'Exactly 2 hours', $user);

        $this->assertNotNull($refund);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame('94.00', $refund->refund_amount);

        $this->travelBack();
    }

    // ─────────────────────────────────────────────────────────
    // 24-hour fee boundary
    // ─────────────────────────────────────────────────────────

    public function test_cancellation_exactly_24_hours_before_uses_6_percent_fee(): void
    {
        // Same whole-second freeze, same reasoning as the 2-hour boundary test.
        $this->travelTo(now()->startOfSecond());

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->copy()->addHours(24))
            ->create(['status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        // hoursRemaining == 24.0 falls in the "<= 24" branch => 6% fee / 94% refund.
        $stripeRefund = StripeRefund::constructFrom([
            'id' => 're_exact_24h',
            'amount' => 9400,
            'status' => 'succeeded',
        ]);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->with($appointment->latestPayment, '94.00')
            ->andReturn($stripeRefund);
        $this->app->instance(StripePaymentService::class, $mock);

        app(AppointmentCancellationService::class)->cancel($appointment);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'admin_fee_percentage' => '6.00',
            'refund_amount' => '94.00',
        ]);

        $this->travelBack();
    }

    public function test_cancellation_just_over_24_hours_before_uses_3_percent_fee(): void
    {
        $this->travelTo(now()->startOfSecond());

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->copy()->addHours(24)->addMinute())
            ->create(['status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $stripeRefund = StripeRefund::constructFrom([
            'id' => 're_over_24h',
            'amount' => 9700,
            'status' => 'succeeded',
        ]);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->with($appointment->latestPayment, '97.00')
            ->andReturn($stripeRefund);
        $this->app->instance(StripePaymentService::class, $mock);

        app(AppointmentCancellationService::class)->cancel($appointment);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'admin_fee_percentage' => '3.00',
            'refund_amount' => '97.00',
        ]);

        $this->travelBack();
    }

    // ─────────────────────────────────────────────────────────
    // Staff exemption — Doctor / Admin / Receptionist
    // ─────────────────────────────────────────────────────────

    public function test_doctor_can_cancel_within_two_hours_with_100_percent_refund(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create();
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        ClinicUser::create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
            'role_id' => $doctorRole->id,
        ]);

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addMinutes(30))
            ->create(['clinic_id' => $clinic->id, 'status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $stripeRefund = StripeRefund::constructFrom(['id' => 're_doctor_exempt', 'amount' => 10000, 'status' => 'succeeded']);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->with($appointment->latestPayment, '100.00')
            ->andReturn($stripeRefund);
        $this->app->instance(StripePaymentService::class, $mock);

        $refund = app(AppointmentCancellationService::class)->cancel($appointment, null, $doctorUser);

        $this->assertNotNull($refund);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'admin_fee_percentage' => '0.00',
            'refund_amount' => '100.00',
            'status' => RefundStatus::Succeeded->value,
        ]);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
    }

    public function test_admin_can_cancel_within_two_hours_with_100_percent_refund(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        ClinicUser::create([
            'clinic_id' => null,
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
        ]);

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addMinutes(30))
            ->create(['clinic_id' => $clinic->id, 'status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $stripeRefund = StripeRefund::constructFrom(['id' => 're_admin_exempt', 'amount' => 10000, 'status' => 'succeeded']);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->with($appointment->latestPayment, '100.00')
            ->andReturn($stripeRefund);
        $this->app->instance(StripePaymentService::class, $mock);

        $refund = app(AppointmentCancellationService::class)->cancel($appointment, null, $admin);

        $this->assertNotNull($refund);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'admin_fee_percentage' => '0.00',
            'refund_amount' => '100.00',
        ]);
    }

    public function test_receptionist_can_cancel_within_two_hours_with_100_percent_refund(): void
    {
        $clinic = Clinic::factory()->create();
        $receptionistUser = User::factory()->create();
        $receptionistRole = Role::firstOrCreate(['name' => 'receptionist']);

        ClinicUser::create([
            'clinic_id' => $clinic->id,
            'user_id' => $receptionistUser->id,
            'role_id' => $receptionistRole->id,
        ]);

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addMinutes(30))
            ->create(['clinic_id' => $clinic->id, 'status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $stripeRefund = StripeRefund::constructFrom(['id' => 're_receptionist_exempt', 'amount' => 10000, 'status' => 'succeeded']);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->with($appointment->latestPayment, '100.00')
            ->andReturn($stripeRefund);
        $this->app->instance(StripePaymentService::class, $mock);

        $refund = app(AppointmentCancellationService::class)->cancel($appointment, null, $receptionistUser);

        $this->assertNotNull($refund);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'admin_fee_percentage' => '0.00',
            'refund_amount' => '100.00',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Missing slot
    // ─────────────────────────────────────────────────────────

    public function test_cancellation_without_slot_is_rejected(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => AppointmentStatus::Scheduled,
            'slot_id' => null,
            'price' => '100.00',
        ]);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldNotReceive('refundDestinationCharge');
        $this->app->instance(StripePaymentService::class, $mock);

        $this->expectException(AppointmentMissingSlotException::class);

        try {
            app(AppointmentCancellationService::class)->cancel($appointment);
        } finally {
            $appointment->refresh();
            $payment->refresh();

            $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
            $this->assertSame(PaymentStatus::Paid, $payment->status);
            $this->assertSame('0.00', $payment->refunded_amount);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Slot release
    // ─────────────────────────────────────────────────────────

    public function test_slot_is_released_after_cancellation_without_refundable_payment(): void
    {
        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addHours(20))
            ->create(['status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $slotId = $appointment->slot_id;

        Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Pending,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldNotReceive('refundDestinationCharge');
        $this->app->instance(StripePaymentService::class, $mock);

        $refund = app(AppointmentCancellationService::class)->cancel($appointment);

        $this->assertNull($refund);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertDatabaseHas('doctor_time_slots', [
            'id' => $slotId,
            'status' => SlotStatus::Available->value,
        ]);
    }

    public function test_slot_is_released_after_zero_refund_cancellation(): void
    {
        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addHours(20))
            ->create(['status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $slotId = $appointment->slot_id;

        // Deposit = 6.00, admin fee within 24h = 6% of price(100) = 6.00 => refund 0.00.
        Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '6.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldNotReceive('refundDestinationCharge');
        $this->app->instance(StripePaymentService::class, $mock);

        $refund = app(AppointmentCancellationService::class)->cancel($appointment);

        $this->assertNotNull($refund);
        $this->assertSame('0.00', $refund->refund_amount);
        $this->assertNull($refund->stripe_refund_id);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertDatabaseHas('doctor_time_slots', [
            'id' => $slotId,
            'status' => SlotStatus::Available->value,
        ]);
    }

    public function test_slot_is_released_after_successful_stripe_refund(): void
    {
        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addHours(20))
            ->create(['status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $slotId = $appointment->slot_id;

        Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $stripeRefund = StripeRefund::constructFrom(['id' => 're_slot_release', 'amount' => 9400, 'status' => 'succeeded']);

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')->once()->andReturn($stripeRefund);
        $this->app->instance(StripePaymentService::class, $mock);

        app(AppointmentCancellationService::class)->cancel($appointment);

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertDatabaseHas('doctor_time_slots', [
            'id' => $slotId,
            'status' => SlotStatus::Available->value,
        ]);
    }

    public function test_slot_is_not_released_when_stripe_refund_fails(): void
    {
        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addHours(20))
            ->create(['status' => AppointmentStatus::Scheduled, 'price' => '100.00']);

        $slotId = $appointment->slot_id;
        $slotStatusBefore = DoctorTimeSlot::find($slotId)->status;

        Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
        ]);

        $appointment->load('latestPayment');

        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->andThrow(StripeRefundFailedException::fromStripeError('Test failure'));
        $this->app->instance(StripePaymentService::class, $mock);

        try {
            app(AppointmentCancellationService::class)->cancel($appointment);
        } catch (StripeRefundFailedException $e) {
            // expected
        }

        $appointment->refresh();

        $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
        $this->assertDatabaseHas('doctor_time_slots', [
            'id' => $slotId,
            'status' => $slotStatusBefore->value,
        ]);
    }
}