<?php

namespace Tests\Feature\Payments;

use App\Core\Enums\StripeAccountType;
use App\Models\Clinic;
use App\Models\ClinicUser;
use App\Models\Doctor;
use App\Models\Role;
use App\Models\User;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Stripe\Account as StripeAccount;
use Stripe\StripeObject;
use Tests\TestCase;

class DoctorStripeConnectOAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeDoctorUser(Clinic $clinic): array
    {
        $user = User::factory()->create();

        $doctor = Doctor::factory()->create([
            'user_id' => $user->id,
        ]);

        $role = Role::firstOrCreate([
            'name' => 'doctor',
        ]);

        ClinicUser::create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        return [$user->fresh(), $doctor];
    }

    public function test_doctor_can_link_an_existing_stripe_account_via_oauth(): void
    {
        config([
            'services.stripe.connect_client_id' => 'ca_test_123',
        ]);

        $clinic = Clinic::factory()->create();

        [$doctorUser, $doctor] = $this->makeDoctorUser($clinic);

        $doctor->update([
            'stripe_connect_id' => null,
            'stripe_account_type' => null,
            'stripe_active' => false,
        ]);

        // Step 1: doctor asks for the OAuth authorize URL.
        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/v1/doctor/stripe/connect-existing');

        $response->assertOk();

        $authorizeUrl = $response->json('data.authorize_url');

        $this->assertNotEmpty($authorizeUrl);
        $this->assertStringContainsString('connect.stripe.com/oauth/authorize', $authorizeUrl);
        $this->assertStringContainsString('client_id=ca_test_123', $authorizeUrl);

        parse_str(parse_url($authorizeUrl, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('state', $query);

        $state = $query['state'];

        $this->assertEquals(
            $doctor->id,
            Cache::get('stripe_connect_oauth_state:' . $state)
        );

        // Step 2: mock the Stripe SDK boundary only — never hit the
        // real Stripe API in tests.
        $fakeOAuthResponse = new StripeObject();
        $fakeOAuthResponse->stripe_user_id = 'acct_existing_doctor_123';

        $fakeAccount = StripeAccount::constructFrom([
            'id' => 'acct_existing_doctor_123',
            'charges_enabled' => true,
            'payouts_enabled' => true,
        ]);

        $serviceMock = Mockery::mock(
            StripePaymentService::class,
            [app(\Stripe\StripeClient::class)]
        )->makePartial();

        // Must come before any shouldReceive() on a protected method.
        $serviceMock->shouldAllowMockingProtectedMethods();

        $serviceMock->shouldReceive('exchangeOAuthCode')
            ->once()
            ->with('valid_auth_code')
            ->andReturn($fakeOAuthResponse);

        $serviceMock->shouldReceive('retrieveAccount')
            ->once()
            ->with('acct_existing_doctor_123')
            ->andReturn($fakeAccount);

        $this->app->instance(StripePaymentService::class, $serviceMock);

        // Step 3: simulate Stripe redirecting the doctor's browser
        // back to our public callback — unauthenticated.
        $callback = $this->get(
            '/api/v1/doctor/stripe/oauth/callback'
            . '?code=valid_auth_code'
            . '&state=' . urlencode($state)
        );

        $callback->assertRedirect();
        $this->assertStringContainsString(
            'stripe_connect=success',
            $callback->headers->get('Location')
        );

        // Step 4: assert the doctor is now linked and payment-ready —
        // no new Stripe account was created, the existing one was used.
        $doctor->refresh();
$this->assertEquals('acct_existing_doctor_123', $doctor->stripe_connect_id);
        $this->assertEquals(StripeAccountType::Standard, $doctor->stripe_account_type);
        $this->assertTrue($doctor->stripe_active);
        $this->assertTrue($doctor->canReceiveOnlinePayments());

        // State must be single-use.
        $this->assertNull(Cache::get('stripe_connect_oauth_state:' . $state));
    }

    public function test_oauth_callback_redirects_with_failure_when_state_is_invalid_or_expired(): void
    {
        $callback = $this->get(
            '/api/v1/doctor/stripe/oauth/callback'
            . '?code=some_code'
            . '&state=nonexistent_state'
        );

        $callback->assertRedirect();
        $this->assertStringContainsString(
            'stripe_connect=failed',
            $callback->headers->get('Location')
        );
    }
}