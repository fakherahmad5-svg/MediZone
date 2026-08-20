<?php

namespace App\Modules\Payments\Controllers;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\PaymentStatus;
use App\Core\Exceptions\ConflictException;
use App\Core\Http\Controllers\BaseController;
use App\Models\Appointment;
use App\Models\Payment;
use App\Modules\Payments\Services\StripePaymentService;
use App\Modules\Scheduling\Services\DoctorTimeSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends BaseController
{
    public function __construct(
        private readonly StripePaymentService $stripe,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $webhookSecret = config('services.stripe.webhook_secret');

        $event = $this->stripe->verifyWebhookSignature(
            $payload,
            $signature,
            $webhookSecret
        );

        if ($this->stripe->hasProcessedEvent($event->id)) {
            return $this->successResponse(null, 'Event already processed.');
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event),
            'checkout.session.expired' => $this->handleCheckoutExpired($event),
            default => Log::info('Unhandled Stripe webhook event type.', [
                'type' => $event->type,
            ]),
        };

        $this->stripe->markEventProcessed(
            $event->id,
            $event->type,
            $event->toArray()
        );

        return $this->successResponse(null, 'Webhook processed.');
    }

    private function handleCheckoutCompleted($event): void
    {
        $session = $event->data->object;

        $payment = Payment::where(
            'stripe_checkout_session_id',
            $session->id
        )->first();

        if (! $payment) {
            Log::warning(
                'Stripe checkout.session.completed for unknown Payment.',
                ['session_id' => $session->id]
            );

            return;
        }

        DB::transaction(function () use ($payment, $session): void {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return;
            }

            if ($payment->status === PaymentStatus::Paid) {
                return;
            }

            $appointment = Appointment::query()
                ->whereKey($payment->appointment_id)
                ->lockForUpdate()
                ->first();

            if (! $appointment) {
                Log::warning(
                    'Stripe checkout.session.completed for Payment without Appointment.',
                    ['payment_id' => $payment->id]
                );

                return;
            }

            /*
             * Re-check the appointment state AFTER acquiring the lock.
             * Prevents a webhook from resurrecting an appointment that
             * was already cancelled by the expiry/failure path.
             */
            if ($appointment->status !== AppointmentStatus::AwaitingPayment) {
                Log::warning(
                    'Payment confirmed for appointment not in AwaitingPayment state — needs manual reconciliation.',
                    [
                        'appointment_id' => $appointment->id,
                        'status' => $appointment->status->value,
                    ]
                );

                return;
            }

            /*
             * The slot was never reserved at booking time — only a
             * read-only availability check happened then (see
             * AppointmentBookingService::book() using
             * findAvailableOrFail(), not lockForBooking()).
             * Reservation happens for the first time here, atomically,
             * inside this same DB transaction.
             *
             * lockForBooking() opens its own DB::transaction() — since
             * we're already inside one, Laravel nests it as a savepoint
             * on the same connection, so this stays atomic with
             * everything else in this method.
             */
            if ($appointment->slot_id) {
                try {
                    app(DoctorTimeSlotService::class)
                        ->lockForBooking($appointment->slot_id);
                } catch (ConflictException $e) {
                    /*
                     * Someone else's payment reserved this slot first.
                     * Stripe DID successfully charge this patient, but
                     * we must not create a second booking for the same
                     * slot. Do not mark Payment/Appointment as
                     * confirmed — needs manual reconciliation (refund).
                     */
                    Log::critical(
                        'Payment succeeded but slot was already booked by another appointment — needs manual reconciliation (refund).',
                        [
                            'appointment_id' => $appointment->id,
                            'payment_id' => $payment->id,
                            'slot_id' => $appointment->slot_id,
                        ]
                    );

                    return;
                }
            }

            $payment->update([
                'status' => PaymentStatus::Paid,
                'stripe_payment_intent_id' =>
                    $session->payment_intent
                    ?? $payment->stripe_payment_intent_id,
                'paid_at' => now(),
            ]);

            $appointment->update([
                'status' => AppointmentStatus::Scheduled,
            ]);
        });
    }

    private function handlePaymentFailed($event): void
    {
        $paymentIntent = $event->data->object;

        $payment = Payment::where(
            'stripe_payment_intent_id',
            $paymentIntent->id
        )->first();

        if (! $payment) {
            Log::warning(
                'Stripe payment_intent.payment_failed for unknown Payment.',
                ['payment_intent_id' => $paymentIntent->id]
            );

            return;
        }

        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return;
            }

            if ($payment->status === PaymentStatus::Paid) {
                return;
            }

            $payment->update(['status' => PaymentStatus::Failed]);

            $this->cancelAwaitingAppointment($payment);
        });
    }

    private function handleCheckoutExpired($event): void
    {
        $session = $event->data->object;

        $payment = Payment::where(
            'stripe_checkout_session_id',
            $session->id
        )->first();

        if (! $payment) {
            Log::warning(
                'Stripe checkout.session.expired for unknown Payment.',
                ['session_id' => $session->id]
            );

            return;
        }

        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return;
            }

            if ($payment->status === PaymentStatus::Paid) {
                // Payment actually succeeded despite the expiry event
                // (a possible race) — do not undo it.
                return;
            }

            if ($payment->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Failed]);
            }

            $this->cancelAwaitingAppointment($payment);
        });
    }

    private function cancelAwaitingAppointment(Payment $payment): void
    {
        $appointment = Appointment::query()
            ->whereKey($payment->appointment_id)
            ->lockForUpdate()
            ->first();

        if (
            ! $appointment ||
            $appointment->status !== AppointmentStatus::AwaitingPayment
        ) {
            return;
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Payment was not completed in time.',
        ]);

        if ($appointment->slot_id) {
            app(DoctorTimeSlotService::class)
                ->release($appointment->slot_id);
        }
    }
}