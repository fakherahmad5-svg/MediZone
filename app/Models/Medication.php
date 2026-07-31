<?php

namespace App\Models;

use App\Core\Enums\MedicationSource;
use App\Core\Enums\MedicationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Medication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source'     => MedicationSource::class,
            'status'     => MedicationStatus::class,
            'start_date' => 'date',
            'end_date'   => 'date',
            'stopped_at' => 'date',
        ];
    }


    public function patientRecord(): BelongsTo
    {
        return $this->belongsTo(PatientRecord::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function isEditableByPatient(): bool
    {
        return $this->source === MedicationSource::SelfReported;
    }
}
