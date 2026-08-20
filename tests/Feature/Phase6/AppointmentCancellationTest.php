<?php

namespace Tests\Feature\Phase6;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\RefundStatus;
use App\Core\Exceptions\StripeRefundFailedException;
use App\Core\Exceptions\DuplicateRefundException;
use App\Core\Exceptions\AppointmentNotCancellableException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Clinic;
use App\Models\ClinicUser;
use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Services\AppointmentCancellationService;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Refund as StripeRefund;
use Tests\TestCase;

class AppointmentCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_cancellation_refunds_94_percent_after_6_percent_admin_fee(): void
    {
        // FIX: slotStartingAt(...) — without this, the appointment's slot
        // defaults to >24h out (AppointmentFactory's base definition), which
        // would exercise the 3%/97% tier instead of the 6%/94% this test
        // name promises. Also fixes the underlying bug where no slot was
        // ever created at all, which would throw AppointmentMissingSlotException.
        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addHours(20))
            ->create([
                'status' => AppointmentStatus::Scheduled,
                'price' => '100.00',
                'cancelled_at' => null,
                'refund_amount' => null,
            ]);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
            'refunded_amount' => '0.00',
            'refunded_at' => null,
            'stripe_payment_intent_id' => 'pi_test_step4_001',
        ]);

        $appointment->load('latestPayment');

        $stripeRefund = StripeRefund::constructFrom([
            'id' => 're_test_step4_001',
            'amount' => 9400,
            'status' => 'succeeded',
        ]);

        $mock = Mockery::mock(StripePaymentService::class);

        $mock->shouldReceive('refundDestinationCharge')
            ->once()
            ->with($appointment->latestPayment, '94.00')
            ->andReturn($stripeRefund);

        $this->app->instance(StripePaymentService::class, $mock);

        $service = app(AppointmentCancellationService::class);

        $refund = $service->cancel($appointment);

        $this->assertInstanceOf(Refund::class, $refund);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'appointment_id' => $appointment->id,
            'original_amount' => '100.00',
            'admin_fee_percentage' => '6.00',
            'admin_fee_amount' => '6.00',
            'refund_amount' => '94.00',
            'status' => RefundStatus::Succeeded->value,
            'stripe_refund_id' => 're_test_step4_001',
        ]);

        $payment->refresh();
        $appointment->refresh();

        $this->assertSame('94.00', $payment->refunded_amount);

        $this->assertSame(
            PaymentStatus::PartiallyRefunded,
            $payment->status
        );

        $this->assertSame(
            AppointmentStatus::Cancelled,
            $appointment->status
        );

        $this->assertSame('94.00', $appointment->refund_amount);
    }
    public function test_online_cancellation_refunds_97_percent_after_3_percent_admin_fee(): void
{
    $appointment = Appointment::factory()
        ->slotStartingAt(now()->addHours(48))
        ->create([
            'status' => AppointmentStatus::Scheduled,
            'price' => '100.00',
        ]);

    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'refunded_amount' => '0.00',
    ]);

    $appointment->load('latestPayment');

    $stripeRefund = StripeRefund::constructFrom([
        'id' => 're_test_far_001',
        'amount' => 9700,
        'status' => 'succeeded',
    ]);

    $mock = Mockery::mock(StripePaymentService::class);

    $mock->shouldReceive('refundDestinationCharge')
        ->once()
        ->with($appointment->latestPayment, '97.00')
        ->andReturn($stripeRefund);

    $this->app->instance(StripePaymentService::class, $mock);

    $refund = app(AppointmentCancellationService::class)
        ->cancel($appointment);

    $this->assertDatabaseHas('refunds', [
        'payment_id' => $payment->id,
        'admin_fee_percentage' => '3.00',
        'admin_fee_amount' => '3.00',
        'refund_amount' => '97.00',
        'status' => RefundStatus::Succeeded->value,
        'stripe_refund_id' => 're_test_far_001',
    ]);

    $this->assertSame(
        PaymentStatus::PartiallyRefunded,
        $payment->fresh()->status
    );

    $this->assertSame(
        AppointmentStatus::Cancelled,
        $appointment->fresh()->status
    );
}
public function test_staff_initiated_cancellation_has_no_admin_fee(): void
{
    $clinic = Clinic::factory()->create();

    $admin = \App\Models\User::factory()->create();

    $adminRole = \App\Models\Role::create([
        'name' => 'admin',
    ]);

    \App\Models\ClinicUser::create([
        'clinic_id' => $clinic->id,
        'user_id' => $admin->id,
        'role_id' => $adminRole->id,
    ]);

    $appointment = Appointment::factory()
        ->slotStartingAt(now()->addHours(20))
        ->create([
            'clinic_id' => $clinic->id,
            'status' => AppointmentStatus::Scheduled,
            'price' => '100.00',
        ]);

    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'refunded_amount' => '0.00',
        'refunded_at' => null,
    ]);

    $appointment->load('latestPayment');

    $stripeRefund = StripeRefund::constructFrom([
        'id' => 're_test_staff_001',
        'amount' => 10000,
        'status' => 'succeeded',
    ]);

    $mock = Mockery::mock(StripePaymentService::class);

    $mock->shouldReceive('refundDestinationCharge')
        ->once()
        ->with($appointment->latestPayment, '100.00')
        ->andReturn($stripeRefund);

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $refund = $service->cancel($appointment, null, $admin);

    $this->assertInstanceOf(Refund::class, $refund);

    $this->assertDatabaseHas('refunds', [
        'payment_id' => $payment->id,
        'appointment_id' => $appointment->id,
        'original_amount' => '100.00',
        'admin_fee_percentage' => '0.00',
        'admin_fee_amount' => '0.00',
        'refund_amount' => '100.00',
        'status' => RefundStatus::Succeeded->value,
        'stripe_refund_id' => 're_test_staff_001',
    ]);

    $payment->refresh();
    $appointment->refresh();

    $this->assertSame('100.00', $payment->refunded_amount);

    $this->assertSame(
        PaymentStatus::Refunded,
        $payment->status
    );

    $this->assertSame(
        AppointmentStatus::Cancelled,
        $appointment->status
    );

    $this->assertSame('100.00', $appointment->refund_amount);
}
public function test_stripe_refund_failure_does_not_cancel_or_modify_payment(): void
{
    $appointment = Appointment::factory()
        ->slotStartingAt(now()->addHours(20))
        ->create([
            'status' => AppointmentStatus::Scheduled,
            'price' => '100.00',
        ]);

    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'refunded_amount' => '0.00',
        'refunded_at' => null,
        'stripe_payment_intent_id' => 'pi_test_failure_001',
    ]);

    $appointment->load('latestPayment');

    $mock = Mockery::mock(StripePaymentService::class);

    $mock->shouldReceive('refundDestinationCharge')
        ->once()
        ->with($appointment->latestPayment, '94.00')
        ->andThrow(
            \App\Core\Exceptions\StripeRefundFailedException::fromStripeError(
                'Test Stripe failure'
            )
        );

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $this->expectException(
        \App\Core\Exceptions\StripeRefundFailedException::class
    );

    try {
        $service->cancel($appointment);
    } finally {
        $appointment->refresh();
        $payment->refresh();

        $this->assertSame(
            AppointmentStatus::Scheduled,
            $appointment->status
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->status
        );

        $this->assertSame(
            '0.00',
            $payment->refunded_amount
        );

        $this->assertNull($payment->refunded_at);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'appointment_id' => $appointment->id,
            'status' => RefundStatus::Failed->value,
            'refund_amount' => '94.00',
            'stripe_refund_id' => null,
        ]);
    }
}
public function test_duplicate_refund_is_rejected(): void
{
    $appointment = Appointment::factory()
        ->slotStartingAt(now()->addHours(20))
        ->create([
            'status' => AppointmentStatus::Scheduled,
            'price' => '100.00',
        ]);

    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'refunded_amount' => '0.00',
        'refunded_at' => null,
        'stripe_payment_intent_id' => 'pi_test_duplicate_001',
    ]);

  Refund::create([
    'payment_id' => $payment->id,
    'appointment_id' => $appointment->id,
    'original_amount' => '100.00',
    'admin_fee_percentage' => '6.00',
    'admin_fee_amount' => '6.00',
    'refund_amount' => '94.00',
    'status' => RefundStatus::Succeeded,
    'stripe_refund_id' => 're_existing_001',
    'cancelled_at' => now(),
    'processed_at' => now(),
]);
    $appointment->load('latestPayment');

    $mock = Mockery::mock(StripePaymentService::class);

    // Stripe must NOT be called.
    $mock->shouldNotReceive('refundDestinationCharge');

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $this->expectException(
        \App\Core\Exceptions\DuplicateRefundException::class
    );

    $service->cancel($appointment);
}public function test_past_appointment_cannot_be_cancelled(): void
{
    $appointment = Appointment::factory()
        ->slotStartingAt(now()->subHour())
        ->create([
            'status' => AppointmentStatus::Scheduled,
            'price' => '100.00',
        ]);

    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'refunded_amount' => '0.00',
        'refunded_at' => null,
    ]);

    $appointment->load('latestPayment');

    $mock = Mockery::mock(StripePaymentService::class);

    // Stripe must never be called.
    $mock->shouldNotReceive('refundDestinationCharge');

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $this->expectException(
        \App\Core\Exceptions\AppointmentAlreadyPastException::class
    );

    try {
        $service->cancel($appointment);
    } finally {
        $appointment->refresh();
        $payment->refresh();

        $this->assertSame(
            AppointmentStatus::Scheduled,
            $appointment->status
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->status
        );

        $this->assertSame(
            '0.00',
            $payment->refunded_amount
        );

        $this->assertSame(
            0,
            Refund::where('payment_id', $payment->id)->count()
        );
    }
}
public function test_already_cancelled_appointment_cannot_be_cancelled_again(): void
{
    $appointment = Appointment::factory()
        ->slotStartingAt(now()->addHours(20))
        ->create([
            'status' => AppointmentStatus::Cancelled,
            'price' => '100.00',
            'cancelled_at' => now(),
        ]);

    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Paid,
        'refunded_amount' => '0.00',
        'refunded_at' => null,
    ]);

    $appointment->load('latestPayment');

    $mock = Mockery::mock(StripePaymentService::class);

    // Stripe must never be called because the appointment
    // is already in a terminal state.
    $mock->shouldNotReceive('refundDestinationCharge');

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $this->expectException(
        \App\Core\Exceptions\AppointmentNotCancellableException::class
    );

    try {
        $service->cancel($appointment);
    } finally {
        $appointment->refresh();
        $payment->refresh();

        $this->assertSame(
            AppointmentStatus::Cancelled,
            $appointment->status
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->status
        );

        $this->assertSame(
            '0.00',
            $payment->refunded_amount
        );

        $this->assertSame(
            0,
            Refund::where('payment_id', $payment->id)->count()
        );
    }
}
public function test_cancellation_without_refundable_payment_cancels_without_refund(): void
{
    $appointment = Appointment::factory()
        ->slotStartingAt(now()->addHours(20))
        ->create([
            'status' => AppointmentStatus::Scheduled,
            'price' => '100.00',
            'cancelled_at' => null,
            'refund_amount' => null,
        ]);

    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '100.00',
        'status' => PaymentStatus::Pending,
        'refunded_amount' => '0.00',
        'refunded_at' => null,
    ]);

    $appointment->load('latestPayment');

    $mock = Mockery::mock(StripePaymentService::class);

    // Stripe must NOT be called because the payment is not refundable.
    $mock->shouldNotReceive('refundDestinationCharge');

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $refund = $service->cancel(
        $appointment,
        'Test cancellation without refundable payment'
    );

    $this->assertNull($refund);

    $appointment->refresh();
    $payment->refresh();

    $this->assertSame(
        AppointmentStatus::Cancelled,
        $appointment->status
    );

    $this->assertSame(
        PaymentStatus::Pending,
        $payment->status
    );

    $this->assertSame(
        '0.00',
        $payment->refunded_amount
    );

    $this->assertNull($payment->refunded_at);

    $this->assertSame(
        0,
        Refund::where('payment_id', $payment->id)->count()
    );

    $this->assertSame(
        'Test cancellation without refundable payment',
        $appointment->cancellation_reason
    );
}
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
        'refunded_at' => null,
    ]);

    $mock = Mockery::mock(StripePaymentService::class);

    // Stripe must never be called because the appointment has no slot.
    $mock->shouldNotReceive('refundDestinationCharge');

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $this->expectException(
        \App\Core\Exceptions\AppointmentMissingSlotException::class
    );

    try {
        $service->cancel($appointment);
    } finally {
        $appointment->refresh();
        $payment->refresh();

        $this->assertSame(
            AppointmentStatus::Scheduled,
            $appointment->status
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->status
        );

        $this->assertSame(
            '0.00',
            $payment->refunded_amount
        );

        $this->assertSame(
            0,
            Refund::where('payment_id', $payment->id)->count()
        );
    }
}public function test_zero_refund_is_recorded_without_calling_stripe(): void
{
    $appointment = Appointment::factory()
        ->slotStartingAt(now()->addHours(20))
        ->create([
            'status' => AppointmentStatus::Scheduled,
            'price' => '100.00',
        ]);

    // Cash deposit = 6.00.
    // Admin fee within 24h = 6% of the full appointment price = 6.00.
    // Therefore refund amount = 0.00.
    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'amount' => '6.00',
        'status' => PaymentStatus::Paid,
        'refunded_amount' => '0.00',
        'refunded_at' => null,
    ]);

    $appointment->load('latestPayment');

    $mock = Mockery::mock(StripePaymentService::class);

    // Stripe must NOT be called for a zero refund.
    $mock->shouldNotReceive('refundDestinationCharge');

    $this->app->instance(StripePaymentService::class, $mock);

    $service = app(AppointmentCancellationService::class);

    $refund = $service->cancel($appointment);

    $this->assertInstanceOf(Refund::class, $refund);

    $this->assertDatabaseHas('refunds', [
        'payment_id' => $payment->id,
        'appointment_id' => $appointment->id,
        'original_amount' => '6.00',
        'admin_fee_percentage' => '6.00',
        'admin_fee_amount' => '6.00',
        'refund_amount' => '0.00',
        'status' => RefundStatus::Succeeded->value,
        'stripe_refund_id' => null,
    ]);

    $payment->refresh();
    $appointment->refresh();

    $this->assertSame(
        '0.00',
        $payment->refunded_amount
    );

    $this->assertNull($payment->refunded_at);

    $this->assertSame(
        PaymentStatus::Paid,
        $payment->status
    );

    $this->assertSame(
        AppointmentStatus::Cancelled,
        $appointment->status
    );

    $this->assertSame(
        '0.00',
        $appointment->refund_amount
    );
}
}