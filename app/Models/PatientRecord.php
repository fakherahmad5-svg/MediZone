<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PatientRecord extends Model
{
    protected $fillable = ['patient_id'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function medicalHistory(): HasOne
    {
        return $this->hasOne(MedicalHistory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function hasMedicalData(): bool
    {
        return $this->medications()->exists()
            || $this->attachments()->exists()
            || $this->medicalHistory->allergies()->exists()
            || $this->medicalHistory->chronicConditions()->exists()
            || $this->medicalHistory->surgeries()->exists()
            || $this->medicalHistory->familyHistories()->exists();
    }
}
