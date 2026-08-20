<?php

namespace App\Modules\Payments\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $isStaff = $user && (
            $user->isSuperAdmin()
            || $user->hasRole('doctor')
            || $user->hasRole('receptionist')
            || $user->hasRole('admin')
        );

        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method?->value,
            'status' => $this->status?->value,
            'refunded_amount' => $this->refunded_amount,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Internal Stripe identifiers are only useful for staff
            // reconciling payments; hidden from the patient's own view.
            'stripe_payment_intent_id' => $this->when($isStaff, $this->stripe_payment_intent_id),
            'stripe_charge_id' => $this->when($isStaff, $this->stripe_charge_id),
        ];
    }
}