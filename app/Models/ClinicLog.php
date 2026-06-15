<?php


namespace App\Models;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicLog extends Model
{
    protected $fillable = [
        'clinic_id',
        'user_id',
        'action',
        'description',
        'entity_type',
        'entity_id',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}