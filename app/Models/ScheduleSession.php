<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSession extends Model
{
    protected $fillable = [
        'schedule_day_id',
        'session_type',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scheduleDay(): BelongsTo
    {
        return $this->belongsTo(ScheduleDay::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(DoctorTimeSlot::class, 'session_id');
    }
}