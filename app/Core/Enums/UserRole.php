<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum UserRole: string
{
    use EnumValues;

    case Admin = 'admin';
    case Doctor = 'doctor';
    case Patient = 'patient';
    case Receptionist = 'receptionist';

    public function dashboard(): string
    {
        return match ($this) {
            self::Admin => 'admin_dashboard',
            self::Doctor => 'doctor_dashboard',
            self::Patient => 'patient_dashboard',
            self::Receptionist => 'receptionist_dashboard',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Doctor => 'Doctor',
            self::Patient => 'Patient',
            self::Receptionist => 'Receptionist',
        };
    }


    public static function selfRegisterable(): array
    {
        return [
            self::Patient->value,
            self::Doctor->value,
            self::Receptionist->value,
        ];
    }
}
