<?php

namespace App\Models;

use App\Core\Enums\ClinicStatus;
use App\Models\ClinicUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Clinic extends Model implements HasMedia
{
    use SoftDeletes,InteractsWithMedia;

    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_RANDOM_LENGTH = 9;

    protected static function booted(): void
    {
        static::creating(function (Clinic $clinic) {
            if (empty($clinic->code)) {
                $clinic->code = self::generateUniqueCode();
            }
        });
    }

    private static function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = self::generateCode();
            if (! self::withTrashed()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate a unique clinic code after multiple attempts.');
    }

    public static function generateCode(): string
    {
        $random = self::randomPart();

        return $random . self::checksumChar($random);
    }

    public static function isValidCodeFormat(string $code): bool
    {
        $code = strtoupper(trim($code));

        if (strlen($code) !== self::CODE_RANDOM_LENGTH + 1) {
            return false;
        }

        $random = substr($code, 0, self::CODE_RANDOM_LENGTH);
        $checksum = substr($code, self::CODE_RANDOM_LENGTH, 1);

        foreach (str_split($random . $checksum) as $char) {
            if (! str_contains(self::CODE_ALPHABET, $char)) {
                return false;
            }
        }

        return self::checksumChar($random) === $checksum;
    }

    private static function randomPart(): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_RANDOM_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    private static function checksumChar(string $random): string
    {
        $alphabet = self::CODE_ALPHABET;
        $sum = 0;

        foreach (str_split($random) as $position => $char) {
            $sum += strpos($alphabet, $char) * ($position + 1);
        }

        return $alphabet[$sum % strlen($alphabet)];
    }
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude'  => 'float',
            'longitude' => 'float',
            'status'    => ClinicStatus::class,
        ];
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
    public function departments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'clinic_departments');
    }

    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    public function doctorDepartments(): HasMany
    {
        return $this->hasMany(DoctorDepartment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }


    // ─── Status Helpers ───────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ClinicStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === ClinicStatus::Active;
    }

    public function isRejected(): bool
    {
        return $this->status === ClinicStatus::Rejected;
    }

    public function isSuspended(): bool
    {
        return $this->status === ClinicStatus::Suspended;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('license')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->singleFile()
            ->useDisk('public');
    }

    public function licenseUrl(): ?string
    {
        return $this->getFirstMediaUrl('license') ?: null;
    }
}
