<?php

namespace App\Policies;

use App\Models\PatientRecord;
use App\Models\User;

class PatientRecordPolicy
{
    public function view(User $user, PatientRecord $record): bool
    {
        return $this->isOwner($user, $record);
    }

    public function update(User $user, PatientRecord $record): bool
    {
        return $this->isOwner($user, $record);
    }

    // ─────────────────────────────────────────────

    private function isOwner(User $user, PatientRecord $record): bool
    {
        return $user->patient?->id === $record->patient_id;
    }
}
