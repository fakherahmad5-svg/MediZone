<?php

namespace App\Modules\Patient\Services;

use App\Core\Enums\UserStatus;
use App\Models\Patient;
use Illuminate\Support\Collection;

class PatientQueryService
{
    /**
     * Search active patients by name, phone, or ID card number.
     * Used by receptionists to locate a patient before booking
     * an appointment or creating a walk-in.
     */
    public function searchForReceptionist(string $query, int $limit = 15): Collection
    {
        $term = trim($query);

        $p= Patient::query()
            ->with('user:id,first_name,last_name,phone,ID_card_number,dob,gender')
            ->whereHas('user', function ($uq) use ($term) {
                $uq->where('status', UserStatus::Active->value)
                    ->where(function ($q) use ($term) {
                        $q->where('ID_card_number', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]);
                    });
            })
            ->limit($limit)
            ->get();
        return $p;
    }
}
