<?php


namespace App\Models;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedTime extends Model
{
    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'block_date',
        'day_of_week',
        'start_time',
        'end_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'block_date' => 'date',
            'created_at' => 'datetime',
            ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function overlaps(\Carbon\Carbon $date, string $startTime, string $endTime): bool
    {
        $matchesDate = $this->block_date?->isSameDay($date) ?? false;
        $matchesRecurring = $this->day_of_week !== null && $this->day_of_week == $date->dayOfWeek;

        if (! $matchesDate && ! $matchesRecurring) {
            return false;
        }

        return $startTime < $this->end_time && $endTime > $this->start_time;
    }
}
