<?php


namespace App\Models;

use App\Core\Enums\AccessAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordAccessLog extends Model
{
    protected $fillable = [
        'access_id',
        'action',
        'entity_type',
        'entity_id',
        'ip_address',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'action'      => AccessAction::class,
            'accessed_at' => 'datetime'
        ];
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(PatientDoctorAccess::class, 'access_id');
    }
}
