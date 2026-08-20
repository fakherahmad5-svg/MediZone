<?php

namespace App\Models;

use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'clinic_id',
        'invoice_id',
        'amount',
        'currency',
        'method',
        'status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_transfer_id',
        'refunded_amount',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'refunded_amount' => 'decimal:2',

            'method'          => PaymentMethod::class,
            'status'          => PaymentStatus::class,
            'paid_at'         => 'datetime',
            'refunded_at'     => 'datetime',

        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }


    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isRefundable(): bool
    {

        return $this->status->isRefundable();
    }

    public function refundableAmount(): string
    {
        return bcsub((string) $this->amount, (string) ($this->refunded_amount ?? '0.00'), 2);
    }
}