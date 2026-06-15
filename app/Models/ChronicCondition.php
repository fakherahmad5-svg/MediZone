<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChronicCondition extends Model
{
    protected $fillable = [
        'medical_history_id',
        'condition_name',
        'diagnosed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return ['diagnosed_at' => 'date'];
    }

    public function medicalHistory(): BelongsTo
    {
        return $this->belongsTo(MedicalHistory::class);
    }
}