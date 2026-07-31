<?php

namespace App\Models;

use App\Core\Enums\AllergySeverity;
use App\Core\Enums\AllergyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allergy extends Model
{
    protected $fillable = [
        'medical_history_id',
        'allergen',
        'reaction',
        'severity',
        'allergen_type'
    ];

    protected function casts(): array
    {
        return [
            'severity' => AllergySeverity::class,
            'allergen_type'=>AllergyType::class,
        ];
    }
    public function medicalHistory(): BelongsTo
    {
        return $this->belongsTo(MedicalHistory::class);
    }
}
