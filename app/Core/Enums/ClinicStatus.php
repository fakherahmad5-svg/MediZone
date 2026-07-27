<?php


namespace App\Core\Enums;

enum ClinicStatus: string
{
    case Pending = 'pending';
    case Active    = 'active';
    case Rejected  = 'rejected';
    case Suspended = 'suspended';
}
