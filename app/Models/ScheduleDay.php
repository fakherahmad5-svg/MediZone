<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleDay extends Model
{
    protected $fillable = [
        'schedule_config_id',
        'day_of_week',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scheduleConfig(): BelongsTo
    {
        return $this->belongsTo(ScheduleConfig::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ScheduleSession::class);
    }
}