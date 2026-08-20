<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patient extends Model
{
   use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'blood_type',
        
        'no_show_count',
        'cash_payment_blocked',
        ];

    protected function casts(): array 
    {
        return 
        [
        'no_show_count' => 'integer',
        'cash_payment_blocked' => 'boolean',
        ]; 
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patientRecord(): HasOne
    {
        return $this->hasOne(PatientRecord::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function doctorAccesses(): HasMany
    {
        return $this->hasMany(PatientDoctorAccess::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DoctorReview::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(DoctorFavorite::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DoctorReport::class);
    }
    public function payments(): HasMany
    {
    return $this->hasMany(Payment::class); 
    }
    public function canBookCash(): bool
    {
        return ! $this->cash_payment_blocked; 
    }
        
    
    }
