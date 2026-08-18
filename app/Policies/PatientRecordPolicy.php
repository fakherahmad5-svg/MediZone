<?php

namespace App\Policies;

use App\Models\PatientRecord;
use App\Models\User;

class PatientRecordPolicy
{
    public function view(User $user, PatientRecord $record): bool
    {
        if ($this->isOwner($user, $record)) {
            return true;
        }

        if ($user->doctor) {
            return app(AccessGuard::class)->levelFor($user->doctor, $record->patient) !== null;
        }

        return false;
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
