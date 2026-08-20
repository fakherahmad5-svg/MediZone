<?php

namespace App\Models;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\CashDepositType;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'doctor_id',
        'slot_id',
        'status',
        'encounter_type',
        'price',
        'cancellation_reason',
        'created_by',
        'notes',
        'payment_method',
        'commission_amount',
        'deposit_amount',
        'remaining_cash_amount',
        'cancelled_at',
        'refund_amount',
        'stripe_payment_intent_id',
        // NEW — financial snapshot, written once at booking time.
        'deposit_type',
        'deposit_percentage',
        'commission_percentage',
    ];

    protected function casts(): array
    {
        return [
            'status'          => AppointmentStatus::class,
            'encounter_type'  => ConsultationType::class,
            'price'           => 'float',

            'payment_method'          => PaymentMethod::class,
            'commission_amount'       => 'decimal:2',
            'deposit_amount'          => 'decimal:2',
            'remaining_cash_amount'   => 'decimal:2',
            'refund_amount'           => 'decimal:2',
            'cancelled_at'            => 'datetime',

            // NEW
            'deposit_type'            => CashDepositType::class,
            'deposit_percentage'      => 'decimal:2',
            'commission_percentage'   => 'decimal:2',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(DoctorTimeSlot::class, 'slot_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function encounter(): HasOne
    {
        return $this->hasOne(Encounter::class);
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function isScheduled(): bool
    {
        return $this->status === AppointmentStatus::Scheduled;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === AppointmentStatus::CheckedIn;
    }

    public function isInProgress(): bool
    {
        return $this->status === AppointmentStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === AppointmentStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === AppointmentStatus::Cancelled;
    }

    public function isActive(): bool
    {
        return ! $this->status->isTerminal();
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === AppointmentStatus::AwaitingPayment;
    }

    public function isCashBooking(): bool
    {
        return $this->payment_method === PaymentMethod::Cash;
    }

    public function isCardBooking(): bool
    {
        return $this->payment_method === PaymentMethod::Card;
    }
}