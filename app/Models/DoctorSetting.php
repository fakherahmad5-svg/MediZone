<?php

namespace App\Models;

use App\Core\Enums\CashDepositType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSetting extends Model
{
    protected $fillable = [
        'doctor_id',
        'cash_deposit_type',
        'cash_deposit_value',
    ];

    protected function casts(): array
    {
        return [
            'cash_deposit_type' => CashDepositType::class,
            'cash_deposit_value' => 'decimal:2',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function isPercentageDeposit(): bool
    {
        return $this->cash_deposit_type->isPercentage();
    }

    public function calculateDeposit(string $price): string
    {
        if ($this->isPercentageDeposit()) {
            $raw = bcmul(
                $price,
                bcdiv((string) $this->cash_deposit_value, '100', 4),
                2
            );
        } else {
            $raw = (string) $this->cash_deposit_value;
        }

        return bccomp($raw, $price, 2) === 1
            ? $price
            : $raw;
    }
}