<?php

namespace App\Models;

use App\Core\Enums\AppointmentPaymentMethod;
use App\Core\Enums\AppointmentPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentPayment extends Model
{
    protected $fillable = [
        'appointment_id',
        'method',
        'status',
        'total_amount',
        'platform_fee_amount',
        'doctor_amount',
        'deposit_percent',
        'deposit_amount',
        'amount_paid',
        'refunded_amount',
        'paid_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'method'               => AppointmentPaymentMethod::class,
            'status'               => AppointmentPaymentStatus::class,
            'total_amount'         => 'float',
            'platform_fee_amount'  => 'float',
            'doctor_amount'        => 'float',
            'deposit_amount'       => 'float',
            'amount_paid'          => 'float',
            'refunded_amount'      => 'float',
            'paid_at'              => 'datetime',
            'settled_at'           => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
