<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Surgery extends Model
{
    protected $fillable = [
        'medical_history_id',
        'surgery_name',
        'surgery_date',
        'notes',
    ];

    protected function casts(): array
    {
        return ['surgery_date' => 'date'];
    }

    public function medicalHistory(): BelongsTo
    {
        return $this->belongsTo(MedicalHistory::class);
    }
}