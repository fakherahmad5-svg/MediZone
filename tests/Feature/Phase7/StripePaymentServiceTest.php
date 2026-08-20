<?php

namespace Tests\Feature\Phase7;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\CashDepositType;
use App\Core\Enums\PaymentMethod;
use App\Core\Exceptions\DoctorNotStripeReadyException;
use App\Core\Exceptions\StripeCheckoutFailedException;
use App\Core\Exceptions\StripeConnectAccountException;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSetting;
use App\Models\StripeEvent;
use App\Models\User;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface as StripeHttpClientInterface;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();
        Mockery::close();
        parent::tearDown();
    }

    protected function makeStripeClient(): StripeClient
    {
        return new StripeClient('sk_test_unit_test');
    }

    protected function makeService(StripeClient $stripe): StripePaymentService
    {
        return new StripePaymentService($stripe);
    }

    protected function mockStripeHttpClient(callable $callback): StripeHttpClientInterface
    {
        $httpClient = Mockery::mock(StripeHttpClientInterface::class);

        $httpClient->shouldReceive('request')->once()->andReturnUsing($callback);

        ApiRequestor::setHttpClient($httpClient);

        return $httpClient;
    }

    protected function mockStripeHttpClientSequence(array $callbacks): StripeHttpClientInterface
    {
        $httpClient = Mockery::mock(StripeHttpClientInterface::class);
        $callIndex = 0;

        $httpClient->shouldReceive('request')
            ->times(count($callbacks))
            ->andReturnUsing(function (...$args) use (&$callIndex, $callbacks) {
                $callback = $callbacks[$callIndex];
                $callIndex++;

                return $callback(...$args);
            });

        ApiRequestor::setHttpClient($httpClient);

        return $httpClient;
    }

    public function test_checkout_session_uses_full_price_for_online_booking(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create(['email' => 'doc@example.com']);
        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'stripe_connect_id' => 'acct_test123',
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

        $this->mockStripeHttpClient(function ($method, $url, $headers, $params, $hasFile, $apiMode) {
            $this->assertSame('post', $method);
            $this->assertStringEndsWith('/v1/checkout/sessions', $url);
            $this->assertFalse($hasFile);
            $this->assertSame('v1', $apiMode);
            $this->assertSame(1, $params['line_items'][0]['quantity']);
            $this->assertSame(10000, $params['line_items'][0]['price_data']['unit_amount']);
            $this->assertSame('usd', $params['line_items'][0]['price_data']['currency']);
            $this->assertSame(1000, $params['payment_intent_data']['application_fee_amount']);
            $this->assertSame('acct_test123', $params['payment_intent_data']['transfer_data']['destination']);
            $this->assertSame('payment', $params['mode']);
            $this->assertSame('1', $params['metadata']['appointment_id']);
            $this->assertArrayNotHasKey('expires_at', $params);

            return [
                json_encode([
                    'id' => 'cs_test_001',
                    'object' => 'checkout.session',
                    'payment_intent' => 'pi_test_001',
                    'mode' => 'payment',
                    'status' => 'open',
                ]),
                200,
                ['request-id' => 'req_test_001'],
            ];
        });

        $service = $this->makeService($this->makeStripeClient());

        $result = $service->createCheckoutSessionForAppointment(
            $appointment,
            'https://example.test/success',
            'https://example.test/cancel'
        );

        $this->assertSame('cs_test_001', $result['session']->id);
        $this->assertSame('pi_test_001', $result['session']->payment_intent);
        $this->assertSame('100.00', $result['payment']->amount);
        $this->assertSame('cs_test_001', $result['payment']->stripe_checkout_session_id);
        $this->assertSame('pi_test_001', $result['payment']->stripe_payment_intent_id);
    }

    public function test_checkout_session_uses_deposit_amount_for_cash_booking(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create(['email' => 'doc@example.com']);
        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'stripe_connect_id' => 'acct_test456',
            'stripe_active' => true,
            'commission_percentage' => '10.00',
        ]);

        DoctorSetting::create([
            'doctor_id' => $doctor->id,
            'cash_deposit_type' => CashDepositType::Percentage,
            'cash_deposit_value' => '20.00',
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'price' => '100.00',
            'payment_method' => PaymentMethod::Cash,
            'status' => AppointmentStatus::AwaitingPayment,
            'deposit_amount' => '20.00',
            'commission_amount' => '10.00',
        ]);

        $this->mockStripeHttpClient(function ($method, $url, $headers, $params) {
            $this->assertSame('post', $method);
            $this->assertStringEndsWith('/v1/checkout/sessions', $url);
            $this->assertSame(2000, $params['line_items'][0]['price_data']['unit_amount']);
            $this->assertSame(1000, $params['payment_intent_data']['application_fee_amount']);
            $this->assertSame('acct_test456', $params['payment_intent_data']['transfer_data']['destination']);
            $this->assertArrayNotHasKey('expires_at', $params);

            return [
                json_encode([
                    'id' => 'cs_test_cash_001',
                    'object' => 'checkout.session',
                    'payment_intent' => 'pi_test_cash_001',
                    'mode' => 'payment',
                    'status' => 'open',
                ]),
                200,
                ['request-id' => 'req_test_cash_001'],
            ];
        });

        $service = $this->makeService($this->makeStripeClient());

        $result = $service->createCheckoutSessionForAppointment(
            $appointment,
            'https://example.test/success',
            'https://example.test/cancel'
        );

        $this->assertSame('20.00', $result['payment']->amount);
        $this->assertSame('cs_test_cash_001', $result['payment']->stripe_checkout_session_id);
        $this->assertSame('pi_test_cash_001', $result['payment']->stripe_payment_intent_id);
    }
    public function test_checkout_session_rejected_when_doctor_not_stripe_ready(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'stripe_connect_id' => null,
            'stripe_active' => false,
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'price' => '100.00',
            'payment_method' => PaymentMethod::Card,
            'status' => AppointmentStatus::AwaitingPayment,
            'deposit_amount' => '100.00',
            'commission_amount' => '0.00',
        ]);

        $service = $this->makeService($this->makeStripeClient());

        $this->expectException(DoctorNotStripeReadyException::class);

        $service->createCheckoutSessionForAppointment(
            $appointment,
            'https://example.test/success',
            'https://example.test/cancel'
        );
    }
    public function test_checkout_session_stripe_api_failure_throws_checkout_exception(): void
    {
        $clinic = Clinic::factory()->create();
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'stripe_connect_id' => 'acct_test789',
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

        $this->mockStripeHttpClient(function ($method, $url) {
            $this->assertSame('post', $method);
            $this->assertStringEndsWith('/v1/checkout/sessions', $url);

            return [
                json_encode(['error' => ['type' => 'api_error', 'message' => 'Stripe is down']]),
                500,
                ['request-id' => 'req_error_001'],
            ];
        });

        $service = $this->makeService($this->makeStripeClient());

        $this->expectException(StripeCheckoutFailedException::class);

        $service->createCheckoutSessionForAppointment(
            $appointment,
            'https://example.test/success',
            'https://example.test/cancel'
        );
    }

    public function test_money_to_cents_still_matches_phase_4_behavior(): void
    {
        $service = $this->makeService($this->makeStripeClient());

        $this->assertSame(10000, $service->moneyToCents('100.00'));
        $this->assertSame(9400, $service->moneyToCents('94.00'));
        $this->assertSame(0, $service->moneyToCents('0.00'));
    }

    public function test_event_is_not_processed_until_marked(): void
    {
        $service = $this->makeService($this->makeStripeClient());

        $this->assertFalse($service->hasProcessedEvent('evt_test_001'));

        $service->markEventProcessed('evt_test_001', 'checkout.session.completed', ['id' => 'evt_test_001']);

        $this->assertTrue($service->hasProcessedEvent('evt_test_001'));
        $this->assertDatabaseHas('stripe_events', [
            'stripe_event_id' => 'evt_test_001',
            'type' => 'checkout.session.completed',
        ]);
    }

    public function test_marking_event_processed_twice_does_not_duplicate(): void
    {
        $service = $this->makeService($this->makeStripeClient());

        $service->markEventProcessed('evt_test_002', 'payment_intent.succeeded', []);
        $service->markEventProcessed('evt_test_002', 'payment_intent.succeeded', []);

        $this->assertSame(1, StripeEvent::where('stripe_event_id', 'evt_test_002')->count());
    }

       public function test_create_connect_account_creates_new_account_and_stores_id(): void
    {
        $doctorUser = User::factory()->create(['email' => 'newdoc@example.com']);

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
            'stripe_connect_id' => null,
        ]);

        $this->mockStripeHttpClient(function ($method, $url, $headers, $params) {
            $this->assertSame('post', $method);
            $this->assertStringEndsWith('/v1/accounts', $url);

            $this->assertSame('express', $params['type']);
            $this->assertSame('US', $params['country']);
            $this->assertSame('newdoc@example.com', $params['email']);

            $this->assertTrue(
                filter_var($params['capabilities']['card_payments']['requested'], FILTER_VALIDATE_BOOLEAN)
            );

            $this->assertTrue(
                filter_var($params['capabilities']['transfers']['requested'], FILTER_VALIDATE_BOOLEAN)
            );

            return [
                json_encode([
                    'id' => 'acct_new_001',
                    'object' => 'account',
                    'type' => 'express',
                    'charges_enabled' => false,
                    'payouts_enabled' => false,
                ]),
                200,
                ['request-id' => 'req_acct_001'],
            ];
        });

        $service = $this->makeService($this->makeStripeClient());

        $account = $service->createConnectAccount($doctor);

        $this->assertSame('acct_new_001', $account->id);
        $this->assertSame(
            'acct_new_001',
            $doctor->fresh()->stripe_connect_id
        );
    }

    public function test_create_connect_account_retrieves_existing_without_creating_duplicate(): void
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'stripe_connect_id' => 'acct_existing_001']);

        $this->mockStripeHttpClient(function ($method, $url) {
            $this->assertSame('get', $method);
            $this->assertStringEndsWith('/v1/accounts/acct_existing_001', $url);

            return [
                json_encode(['id' => 'acct_existing_001', 'object' => 'account', 'type' => 'express', 'charges_enabled' => true, 'payouts_enabled' => true]),
                200,
                ['request-id' => 'req_acct_002'],
            ];
        });

        $service = $this->makeService($this->makeStripeClient());
        $account = $service->createConnectAccount($doctor);

        $this->assertSame('acct_existing_001', $account->id);
    }

    public function test_create_connect_account_throws_on_stripe_api_failure(): void
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'stripe_connect_id' => null]);

        $this->mockStripeHttpClient(fn () => [
            json_encode(['error' => ['type' => 'api_error', 'message' => 'Connect is down']]),
            500,
            ['request-id' => 'req_acct_fail'],
        ]);

        $service = $this->makeService($this->makeStripeClient());

        $this->expectException(StripeConnectAccountException::class);

        $service->createConnectAccount($doctor);
    }

    public function test_create_onboarding_link_creates_account_first_when_missing(): void
    {
        $doctorUser = User::factory()->create(['email' => 'onboarding@example.com']);
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'stripe_connect_id' => null]);

        $this->mockStripeHttpClientSequence([
            function ($method, $url) {
                $this->assertSame('post', $method);
                $this->assertStringEndsWith('/v1/accounts', $url);

                return [
                    json_encode(['id' => 'acct_new_002', 'object' => 'account', 'charges_enabled' => false, 'payouts_enabled' => false]),
                    200,
                    ['request-id' => 'req_link_001'],
                ];
            },
            function ($method, $url, $headers, $params) {
                $this->assertSame('post', $method);
                $this->assertStringEndsWith('/v1/account_links', $url);
                $this->assertSame('acct_new_002', $params['account']);
                $this->assertSame('https://example.test/refresh', $params['refresh_url']);
                $this->assertSame('https://example.test/return', $params['return_url']);
                $this->assertSame('account_onboarding', $params['type']);

                return [
                    json_encode(['object' => 'account_link', 'url' => 'https://connect.stripe.com/setup/e/acct_new_002/xyz', 'expires_at' => now()->addMinutes(5)->timestamp]),
                    200,
                    ['request-id' => 'req_link_002'],
                ];
            },
        ]);

        $service = $this->makeService($this->makeStripeClient());

        $link = $service->createOnboardingLink($doctor, 'https://example.test/refresh', 'https://example.test/return');

        $this->assertStringContainsString('acct_new_002', $link->url);
        $this->assertSame('acct_new_002', $doctor->fresh()->stripe_connect_id);
    }

    public function test_create_onboarding_link_skips_account_creation_when_already_exists(): void
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'stripe_connect_id' => 'acct_already_003']);

        $this->mockStripeHttpClient(function ($method, $url, $headers, $params) {
            $this->assertSame('post', $method);
            $this->assertStringEndsWith('/v1/account_links', $url);
            $this->assertSame('acct_already_003', $params['account']);

            return [
                json_encode(['object' => 'account_link', 'url' => 'https://connect.stripe.com/setup/e/acct_already_003/abc', 'expires_at' => now()->addMinutes(5)->timestamp]),
                200,
                ['request-id' => 'req_link_003'],
            ];
        });

        $service = $this->makeService($this->makeStripeClient());

        $link = $service->createOnboardingLink($doctor, 'https://example.test/refresh', 'https://example.test/return');

        $this->assertStringContainsString('acct_already_003', $link->url);
    }

    public function test_refresh_account_status_marks_active_when_charges_and_payouts_enabled(): void
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'stripe_connect_id' => 'acct_status_001', 'stripe_active' => false]);

        $this->mockStripeHttpClient(function ($method, $url) {
            $this->assertSame('get', $method);
            $this->assertStringEndsWith('/v1/accounts/acct_status_001', $url);

            return [
                json_encode(['id' => 'acct_status_001', 'object' => 'account', 'charges_enabled' => true, 'payouts_enabled' => true]),
                200,
                ['request-id' => 'req_status_001'],
            ];
        });

        $service = $this->makeService($this->makeStripeClient());
        $status = $service->refreshAccountStatus($doctor);

        $this->assertTrue($status['charges_enabled']);
        $this->assertTrue($status['payouts_enabled']);
        $this->assertTrue($doctor->fresh()->stripe_active);
    }

    public function test_refresh_account_status_marks_inactive_when_payouts_disabled(): void
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'stripe_connect_id' => 'acct_status_002', 'stripe_active' => true]);

        $this->mockStripeHttpClient(fn () => [
            json_encode(['id' => 'acct_status_002', 'object' => 'account', 'charges_enabled' => true, 'payouts_enabled' => false]),
            200,
            ['request-id' => 'req_status_002'],
        ]);

        $service = $this->makeService($this->makeStripeClient());
        $status = $service->refreshAccountStatus($doctor);

        $this->assertFalse($status['payouts_enabled']);
        $this->assertFalse($doctor->fresh()->stripe_active);
    }

    public function test_refresh_account_status_throws_when_doctor_has_no_connect_id(): void
    {
        $doctorUser = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'stripe_connect_id' => null]);

        $service = $this->makeService($this->makeStripeClient());

        $this->expectException(DoctorNotStripeReadyException::class);

        $service->refreshAccountStatus($doctor);
    }
}