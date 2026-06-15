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
        return ['block_date' => 'date'];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}