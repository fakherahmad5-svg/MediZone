<?php

namespace App\Modules\Payments\Services;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\PaymentStatus;
use App\Core\Exceptions\AppointmentNotAwaitingPaymentException;
use App\Core\Exceptions\DomainValidationException;
use App\Core\Exceptions\DoctorNotStripeReadyException;
use App\Core\Exceptions\DuplicateCheckoutException;
use App\Core\Exceptions\StripeCheckoutFailedException;
use App\Core\Exceptions\StripeConnectAccountException;
use App\Core\Exceptions\StripeRefundFailedException;
use App\Core\Exceptions\StripeWebhookVerificationException;
use App\Core\Enums\StripeAccountType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\StripeEvent;
use Illuminate\Support\Facades\DB;
use Stripe\Account as StripeAccount;
use Stripe\AccountLink;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Event as StripeSdkEvent;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Refund as StripeRefund;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\OAuth;
use Stripe\Stripe;

class StripePaymentService
{
    public function __construct(
        protected StripeClient $stripe
    ) {
    }

    // ─────────────────────────────────────────────────────────────
    // Stripe Connect — doctor onboarding
    // ─────────────────────────────────────────────────────────────

    public function createConnectAccount(
        Doctor $doctor,
        string $country = 'US'
    ): StripeAccount {
        if ($doctor->stripe_connect_id) {
            return $this->retrieveAccount($doctor->stripe_connect_id);
        }

        try {
            $account = $this->callStripe(fn () => $this->stripe->accounts->create([
                'type' => 'express',
                'country' => $country,
                'email' => $doctor->user?->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]));
        } catch (ApiErrorException $e) {
            throw StripeConnectAccountException::fromStripeError(
                $e->getMessage()
            );
        }

        $doctor->update([
            'stripe_connect_id' => $account->id,
            'stripe_account_type' => StripeAccountType::Express,
        ]);

        return $account;
    }

    public function createOnboardingLink(
        Doctor $doctor,
        string $refreshUrl,
        string $returnUrl,
        string $country = 'US'
    ): AccountLink {
        if (! $doctor->stripe_connect_id) {
            $this->createConnectAccount($doctor, $country);
            $doctor->refresh();
        }

        try {
            return $this->callStripe(fn () => $this->stripe->accountLinks->create([
                'account' => $doctor->stripe_connect_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]));
        } catch (ApiErrorException $e) {
            throw StripeConnectAccountException::fromStripeError(
                $e->getMessage()
            );
        }
    }

    public function refreshAccountStatus(Doctor $doctor): array
    {
        if (! $doctor->stripe_connect_id) {
            throw DoctorNotStripeReadyException::forDoctor($doctor->id);
        }

        $account = $this->retrieveAccount($doctor->stripe_connect_id);

        $chargesEnabled = (bool) $account->charges_enabled;
        $payoutsEnabled = (bool) $account->payouts_enabled;

        $doctor->update([
            'stripe_active' => $chargesEnabled && $payoutsEnabled,
        ]);

        return [
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
        ];
    }
// ─────────────────────────────────────────────────────────────
    // Stripe Connect — linking an existing account (OAuth / Standard)
    // ─────────────────────────────────────────────────────────────

    private const OAUTH_STATE_CACHE_PREFIX = 'stripe_connect_oauth_state:';
    private const OAUTH_STATE_TTL_MINUTES = 15;

    public function buildOAuthAuthorizeUrl(Doctor $doctor, string $redirectUrl): string
    {
        $clientId = config('services.stripe.connect_client_id');

        if (! $clientId) {
            throw StripeConnectAccountException::fromStripeError(
                'Stripe Connect OAuth client_id is not configured.'
            );
        }

        $state = Str::random(40);

        Cache::put(
            self::OAUTH_STATE_CACHE_PREFIX . $state,
            $doctor->id,
            now()->addMinutes(self::OAUTH_STATE_TTL_MINUTES)
        );

        $query = http_build_query([
            'response_type' => 'code',
            'scope' => 'read_write',
            'client_id' => $clientId,
            'state' => $state,
            'redirect_uri' => $redirectUrl,
        ]);

        return "https://connect.stripe.com/oauth/authorize?{$query}";
    }

    public function linkExistingAccountViaOAuth(string $code, string $state): Doctor
    {
        $cacheKey = self::OAUTH_STATE_CACHE_PREFIX . $state;
        $doctorId = Cache::get($cacheKey);

        if (! $doctorId) {
            throw StripeConnectAccountException::fromStripeError(
                'This Stripe connection link is invalid or has expired.'
            );
        }

        Cache::forget($cacheKey);

        $doctor = Doctor::query()->find($doctorId);

        if (! $doctor) {
            throw StripeConnectAccountException::fromStripeError(
                'The doctor associated with this Stripe connection could not be found.'
            );
        }

        $response = $this->exchangeOAuthCode($code);

        $connectedAccountId = $response->stripe_user_id;

        $account = $this->retrieveAccount($connectedAccountId);

        $doctor->update([
            'stripe_connect_id' => $connectedAccountId,
            'stripe_account_type' => StripeAccountType::Standard,
            'stripe_active' => (bool) $account->charges_enabled
                && (bool) $account->payouts_enabled,
        ]);

        return $doctor->refresh();
    }

    protected function exchangeOAuthCode(string $code): \Stripe\StripeObject
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            return $this->callStripe(fn () => OAuth::token([
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]));
        } catch (ApiErrorException $e) {
            throw StripeConnectAccountException::fromStripeError(
                $e->getMessage()
            );
        }
    }

    protected function retrieveAccount(string $accountId): StripeAccount
    {
        try {
            return $this->callStripe(fn () => $this->stripe->accounts->retrieve($accountId));
        } catch (ApiErrorException $e) {
            throw StripeConnectAccountException::fromStripeError(
                $e->getMessage()
            );
        }
    }

    /**
     * Stripe sometimes attaches a "stripe-notice" response header —
     * e.g. the Accounts v2 migration nudge for newly created
     * platforms — which stripe-php surfaces via
     * trigger_error(E_USER_WARNING). It is purely informational: the
     * underlying API call has already succeeded or failed on its own
     * terms by this point, via the normal Stripe\Exception\ApiErrorException
     * path (handleErrorResponse()), which is completely untouched by
     * this method and still bubbles up normally to every catch block
     * below.
     *
 * Laravel's default error handler escalates ANY PHP warning into
     * a fatal ErrorException, which would incorrectly abort an
     * otherwise-successful Stripe call just because of this advisory
     * notice. We log the notice instead of letting it propagate, and
     * always restore the previous handler immediately after.
     */
    private function callStripe(callable $call)
    {
        set_error_handler(function (int $errno, string $errstr): bool {
            if ($errno === E_USER_WARNING) {
                Log::info('Stripe SDK notice (non-fatal): ' . $errstr);

                return true;
            }

            return false;
        });

        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Checkout — snapshot-driven (UNCHANGED)
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array{session: CheckoutSession, payment: Payment}
     */
    public function createCheckoutSessionForAppointment(
        Appointment $appointment,
        string $successUrl,
        string $cancelUrl
    ): array {
        return DB::transaction(function () use (
            $appointment,
            $successUrl,
            $cancelUrl
        ): array {
            $appointment = Appointment::query()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($appointment->status !== AppointmentStatus::AwaitingPayment) {
                throw AppointmentNotAwaitingPaymentException::forAppointment(
                    $appointment->id
                );
            }

            $this->assertNoDuplicateCheckout($appointment);

            $doctor = $appointment->doctor;

            if (! $doctor || ! $doctor->canReceiveOnlinePayments()) {
                throw DoctorNotStripeReadyException::forDoctor(
                    $appointment->doctor_id
                );
            }

            $checkoutAmount = $this->resolveCheckoutAmount($appointment);

            $applicationFee = $this->resolveApplicationFee(
                $appointment,
                $checkoutAmount
            );

            $amountCents = $this->moneyToCents($checkoutAmount);
            $applicationFeeCents = $this->moneyToCents($applicationFee);

            try {
                $session = $this->stripe->checkout->sessions->create(
                    [
                        'mode' => 'payment',
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,

                        'line_items' => [[
                            'quantity' => 1,
                            'price_data' => [
                                'currency' => 'usd',
                                'unit_amount' => $amountCents,
                                'product_data' => [
                                    'name' => "Appointment #{$appointment->id}",
                                ],
                            ],
                        ]],

                        'payment_intent_data' => [
                            'transfer_data' => [
                                'destination' => $doctor->stripe_connect_id,
                            ],
                            'application_fee_amount' => $applicationFeeCents,
                        ],

                        'metadata' => [
                            'appointment_id' => (string) $appointment->id,
                        ],
                    ],
                    [
                        'idempotency_key' => "checkout_appointment_{$appointment->id}",
                    ]
                );
            } catch (ApiErrorException $e) {
                throw StripeCheckoutFailedException::fromStripeError(
                    $e->getMessage()
                );
            }
$payment = Payment::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
                'clinic_id' => $appointment->clinic_id,
                'amount' => $checkoutAmount,
                'currency' => 'usd',
                'method' => $appointment->payment_method,
                'status' => PaymentStatus::Pending,
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => $session->payment_intent,
            ]);

            return [
                'session' => $session,
                'payment' => $payment,
            ];
        });
    }

    protected function assertNoDuplicateCheckout(
        Appointment $appointment
    ): void {
        $existing = Payment::where('appointment_id', $appointment->id)
            ->whereIn('status', [
                PaymentStatus::Pending,
                PaymentStatus::Paid,
            ])
            ->exists();

        if ($existing) {
            throw DuplicateCheckoutException::forAppointment(
                $appointment->id
            );
        }
    }

    protected function resolveCheckoutAmount(
        Appointment $appointment
    ): string {
        if ($appointment->deposit_amount === null) {
            throw new DomainValidationException(
                "Appointment #{$appointment->id} has no deposit amount snapshot — cannot start checkout."
            );
        }

        return (string) $appointment->deposit_amount;
    }

    protected function resolveApplicationFee(
        Appointment $appointment,
        string $checkoutAmount
    ): string {
        $commission = (string) (
            $appointment->commission_amount ?? '0.00'
        );

        return bccomp($commission, $checkoutAmount, 2) > 0
            ? $checkoutAmount
            : $commission;
    }

    // ─────────────────────────────────────────────────────────────
    // Refunds (UNCHANGED)
    // ─────────────────────────────────────────────────────────────

    public function refundDestinationCharge(
        Payment $payment,
        string $refundAmount
    ): StripeRefund {
        $refundAmountCents = $this->moneyToCents($refundAmount);

        try {
            return $this->stripe->refunds->create([
                'payment_intent' => $payment->stripe_payment_intent_id,
                'amount' => $refundAmountCents,
                'reverse_transfer' => true,
                'refund_application_fee' => true,
            ]);
        } catch (ApiErrorException $e) {
            throw StripeRefundFailedException::fromStripeError(
                $e->getMessage()
            );
        }
    }

    public function moneyToCents(string $amount): int
    {
        [$dollars, $cents] = array_pad(
            explode('.', $amount),
            2,
            '00'
        );

        $cents = str_pad(
            substr($cents, 0, 2),
            2,
            '0'
        );

        return ((int) $dollars) * 100 + ((int) $cents);
    }

    // ─────────────────────────────────────────────────────────────
    // Webhook idempotency (UNCHANGED)
    // ─────────────────────────────────────────────────────────────

    public function hasProcessedEvent(
        string $stripeEventId
    ): bool {
        return StripeEvent::where('stripe_event_id', $stripeEventId)
            ->whereNotNull('processed_at')
            ->exists();
    }

    public function markEventProcessed(
        string $stripeEventId,
        string $type,
        array $payload
    ): StripeEvent {
        return StripeEvent::updateOrCreate(
            ['stripe_event_id' => $stripeEventId],
            [
                'type' => $type,
                'payload' => $payload,
                'processed_at' => now(),
            ]
        );
    }// ─────────────────────────────────────────────────────────────
    // Webhook signature verification (UNCHANGED)
    // ─────────────────────────────────────────────────────────────

    public function verifyWebhookSignature(
        string $payload,
        string $signatureHeader,
        string $webhookSecret
    ): StripeSdkEvent {
        try {
            return Webhook::constructEvent(
                $payload,
                $signatureHeader,
                $webhookSecret
            );
        } catch (SignatureVerificationException $e) {
            throw StripeWebhookVerificationException::fromError(
                $e->getMessage()
            );
        } catch (\UnexpectedValueException $e) {
            throw StripeWebhookVerificationException::fromError(
                'Invalid payload: ' . $e->getMessage()
            );
        }
    }
}