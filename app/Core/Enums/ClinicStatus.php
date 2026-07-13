<?php


namespace App\Core\Enums;

enum ClinicStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
