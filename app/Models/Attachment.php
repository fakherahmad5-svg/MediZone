<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'patient_record_id',
        'uploaded_by',
        'file_path',
        'type',
        'mime_type',
        'file_size',
        'checksum',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    public function patientRecord(): BelongsTo
    {
        return $this->belongsTo(PatientRecord::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}