<?php

namespace Tests\Feature\Phase7;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\SlotStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Payment;
use App\Modules\Payments\Controllers\StripeWebhookController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StripeWebhookEventsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.stripe.webhook_secret', self::SECRET);
    }

    private function signedRequest(array $eventBody): Request
    {
        $payload = json_encode($eventBody);
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, self::SECRET);
        $header = "t={$timestamp},v1={$signature}";

        return Request::create(
            '/webhooks/stripe',
            'POST',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $header],
            $payload
        );
    }

    private function makeAwaitingAppointmentWithPayment(): array
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = \App\Models\User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $appointment = Appointment::factory()
            ->slotStartingAt(now()->addHours(20))
            ->create([
                'clinic_id' => $clinic->id,
                'doctor_id' => $doctor->id,
                'status' => AppointmentStatus::AwaitingPayment,
                'payment_method' => PaymentMethod::Card,
                'price' => '100.00',
            ]);

        $payment = Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_webhook_test',
            'stripe_payment_intent_id' => 'pi_webhook_test',
        ]);

        return [$appointment, $payment];
    }

    public function test_checkout_completed_marks_payment_paid_and_appointment_scheduled(): void
    {
        [$appointment, $payment] = $this->makeAwaitingAppointmentWithPayment();
        $slotId = $appointment->slot_id;

        $request = $this->signedRequest([
            'id' => 'evt_completed_001',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_webhook_test',
                'payment_intent' => 'pi_webhook_test',
            ]],
        ]);

        app(StripeWebhookController::class)->handle($request);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(AppointmentStatus::Scheduled, $appointment->fresh()->status);
        $this->assertDatabaseHas('doctor_time_slots', ['id' => $slotId, 'status' => SlotStatus::Booked->value]);
    }

    public function test_payment_failed_cancels_appointment_and_releases_slot(): void
    {
        [$appointment, $payment] = $this->makeAwaitingAppointmentWithPayment();
        $slotId = $appointment->slot_id;

        $request = $this->signedRequest([
            'id' => 'evt_failed_001',
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_webhook_test']],
        ]);

        app(StripeWebhookController::class)->handle($request);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertDatabaseHas('doctor_time_slots', ['id' => $slotId, 'status' => SlotStatus::Available->value]);
    }

    public function test_checkout_expired_cancels_appointment_and_releases_slot(): void
    {
        [$appointment, $payment] = $this->makeAwaitingAppointmentWithPayment();
        $slotId = $appointment->slot_id;

        $request = $this->signedRequest([
            'id' => 'evt_expired_001',
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => ['object' => ['id' => 'cs_webhook_test']],
        ]);

        app(StripeWebhookController::class)->handle($request);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertDatabaseHas('doctor_time_slots', ['id' => $slotId, 'status' => SlotStatus::Available->value]);
    }

    public function test_duplicate_event_is_not_processed_twice(): void
    {
        [$appointment, $payment] = $this->makeAwaitingAppointmentWithPayment();

        $request = $this->signedRequest([
            'id' => 'evt_dup_001',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_webhook_test',
                'payment_intent' => 'pi_webhook_test',
            ]],
        ]);

        app(StripeWebhookController::class)->handle($this->signedRequest([
            'id' => 'evt_dup_001',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_webhook_test', 'payment_intent' => 'pi_webhook_test']],
        ]));

        $paidAt = $payment->fresh()->paid_at;

        app(StripeWebhookController::class)->handle($this->signedRequest([
            'id' => 'evt_dup_001',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_webhook_test', 'payment_intent' => 'pi_webhook_test']],
        ]));

        $this->assertSame(1, \App\Models\StripeEvent::where('stripe_event_id', 'evt_dup_001')->count());
        $this->assertEquals($paidAt, $payment->fresh()->paid_at);
    }
}