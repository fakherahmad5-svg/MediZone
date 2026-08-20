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
            $account = $this->stripe->accounts->create([
                'type' => 'express',
                'country' => $country,
                'email' => $doctor->user?->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);
        } catch (ApiErrorException $e) {
            throw StripeConnectAccountException::fromStripeError(
                $e->getMessage()
            );
        }

        $doctor->update([
            'stripe_connect_id' => $account->id,
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
            return $this->stripe->accountLinks->create([
                'account' => $doctor->stripe_connect_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);
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

    protected function retrieveAccount(string $accountId): StripeAccount
    {
        try {
            return $this->stripe->accounts->retrieve($accountId);
        } catch (ApiErrorException $e) {
            throw StripeConnectAccountException::fromStripeError(
                $e->getMessage()
            );
        }
    }
 // ─────────────────────────────────────────────────────────────
    // Checkout — snapshot-driven
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
            /*
             * Lock the appointment row first.
             *
             * This prevents two concurrent checkout requests
             * from both passing the status/payment checks.
             */
            $appointment = Appointment::query()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Re-check status AFTER acquiring the lock.
             */
            if ($appointment->status !== AppointmentStatus::AwaitingPayment) {
                throw AppointmentNotAwaitingPaymentException::forAppointment(
                    $appointment->id
                );
            }

            /*
             * Re-check duplicate checkout AFTER acquiring the lock.
             */
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

    /**
     * Reads the amount actually charged through Stripe from the
     * appointment's booking-time snapshot.
     */
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

    /**
     * The platform commission is snapshotted at booking time
     * against the FULL appointment price.
     */
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
    // Refunds
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
    // Webhook idempotency
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
    }
  // ─────────────────────────────────────────────────────────────
    // Webhook signature verification
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