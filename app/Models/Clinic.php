<?php

namespace App\Models;

use App\Models\ClinicUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Clinic extends Model implements HasMedia
{
    use SoftDeletes,InteractsWithMedia;
    protected $fillable = [
        'name',
        'owner_id',
        'phone',
        'email',
        'address',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'float',
            'longitude' => 'float',
        ];
    }

    public function departments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'clinic_departments');
    }

    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('license')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->singleFile()
            ->useDisk('public');
    }
}
