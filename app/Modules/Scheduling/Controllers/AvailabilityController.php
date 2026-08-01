<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\Doctor;
use App\Models\DoctorTimeSlot;
use Illuminate\Http\Request;

class AvailabilityController extends BaseController
{
    public function index(Request $request, Doctor $doctor)
    {
        $date = $request->query('date', now()->toDateString());

        $slots = DoctorTimeSlot::where('doctor_id', $doctor->id)
            ->where('status', 'available')
            ->whereDate('starts_at', $date)
            ->orderBy('starts_at')
            ->get(['id', 'clinic_id', 'starts_at', 'ends_at', 'status']);

        return $this->successResponse($slots, 'Available slots retrieved successfully.');
    }
}
