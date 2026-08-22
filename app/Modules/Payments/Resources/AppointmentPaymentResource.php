<?php

namespace App\Modules\Payments\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\AppointmentPayment
 */
class AppointmentPaymentResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'appointment_id'      => $this->appointment_id,
            'method'              => $this->method instanceof \BackedEnum ? $this->method->value : $this->method,
            'status'              => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'total_amount'        => (float) $this->total_amount,
            'platform_fee_amount' => (float) $this->platform_fee_amount,
            'doctor_amount'       => (float) $this->doctor_amount,
            'deposit_percent'     => $this->deposit_percent,
            'deposit_amount'      => $this->deposit_amount !== null ? (float) $this->deposit_amount : null,
            'amount_paid'         => (float) $this->amount_paid,
            'refunded_amount'     => (float) $this->refunded_amount,
            'paid_at'             => $this->formatDate($this->paid_at),
            'settled_at'          => $this->formatDate($this->settled_at),
        ];
    }
}
