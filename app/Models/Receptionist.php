<?php

namespace App\Models;

use App\Core\Enums\ReceptionistsStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receptionist extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReceptionistsStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === ReceptionistsStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ReceptionistsStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ReceptionistsStatus::Rejected;
    }

    public function isSuspended(): bool
    {
        return $this->status === ReceptionistsStatus::Suspended;
    }
}
