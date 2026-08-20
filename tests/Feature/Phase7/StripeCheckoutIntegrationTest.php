<?php

namespace Tests\Feature\Phase7;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Exceptions\DuplicateCheckoutException;
use App\Core\Exceptions\StripeWebhookVerificationException;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\User;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface as StripeHttpClientInterface;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeCheckoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        Mockery::close();
        parent::tearDown();
    }

    protected function makeService(): StripePaymentService
    {
        return new StripePaymentService(new StripeClient('sk_test_unit_test'));
    }

    public function test_duplicate_checkout_is_rejected_when_pending_payment_exists(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'stripe_connect_id' => 'acct_dup_001',
            'stripe_active' => true,
            'commission_percentage' => '10.00',
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'price' => '100.00',
            'payment_method' => PaymentMethod::Card,
            'status' => AppointmentStatus::AwaitingPayment,
            'deposit_amount' => '100.00',
            'commission_amount' => '10.00',
        ]);

        Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Pending,
        ]);

        $service = $this->makeService();

        $this->expectException(DuplicateCheckoutException::class);

        $service->createCheckoutSessionForAppointment(
            $appointment,
            'https://example.test/success',
            'https://example.test/cancel'
        );
    }

    public function test_checkout_allowed_when_prior_payment_failed(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'stripe_connect_id' => 'acct_dup_002',
            'stripe_active' => true,
            'commission_percentage' => '10.00',
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'price' => '100.00',
            'payment_method' => PaymentMethod::Card,
            'status' => AppointmentStatus::AwaitingPayment,
            'deposit_amount' => '100.00',
            'commission_amount' => '10.00',
        ]);

        Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'amount' => '100.00',
            'status' => PaymentStatus::Failed,
        ]);

        $httpClient = Mockery::mock(StripeHttpClientInterface::class);
        $httpClient->shouldReceive('request')->once()->andReturn([
            json_encode(['id' => 'cs_retry_001', 'object' => 'checkout.session', 'payment_intent' => 'pi_retry_001', 'mode' => 'payment', 'status' => 'open']),
            200,
            ['request-id' => 'req_retry_001'],
        ]);
        ApiRequestor::setHttpClient($httpClient);

        $service = $this->makeService();

        $result = $service->createCheckoutSessionForAppointment(
            $appointment,
            'https://example.test/success',
            'https://example.test/cancel'
        );

        $this->assertSame('cs_retry_001', $result['payment']->stripe_checkout_session_id);
    }

    public function test_webhook_signature_verification_rejects_invalid_signature(): void
    {
        $service = $this->makeService();

        $this->expectException(StripeWebhookVerificationException::class);

        $service->verifyWebhookSignature(
            json_encode(['id' => 'evt_fake']),
            'invalid_signature_header',
            'whsec_test_secret'
        );
    }

    public function test_webhook_signature_verification_accepts_valid_signature(): void
    {
        $secret = 'whsec_test_secret';
        $payload = json_encode([
            'id' => 'evt_valid_001',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test']],
        ]);

        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        $service = $this->makeService();
        $event = $service->verifyWebhookSignature($payload, $header, $secret);

        $this->assertSame('evt_valid_001', $event->id);
        $this->assertSame('checkout.session.completed', $event->type);
    }
}