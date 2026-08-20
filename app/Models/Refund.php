<?php

namespace App\Models;

use App\Core\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'payment_id',
        'appointment_id',
        'initiated_by',
        'original_amount',
        'admin_fee_percentage',
        'admin_fee_amount',
        'refund_amount',
        'status',
        'stripe_refund_id',
        'failure_reason',
        'cancellation_reason',
        'cancelled_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount'      => 'decimal:2',
            'admin_fee_percentage' => 'decimal:2',
            'admin_fee_amount'     => 'decimal:2',
            'refund_amount'        => 'decimal:2',
            'status'               => RefundStatus::class,
            'cancelled_at'         => 'datetime',
            'processed_at'         => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isPending(): bool
    {
        return $this->status === RefundStatus::Pending;
    }

    public function isSucceeded(): bool
    {
        return $this->status === RefundStatus::Succeeded;
    }

    public function isFailed(): bool
    {
        return $this->status === RefundStatus::Failed;
    }
}