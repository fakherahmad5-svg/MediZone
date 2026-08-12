<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Models\Doctor;
use App\Modules\Scheduling\Requests\AvailabilityQueryRequest;
use App\Modules\Scheduling\Resources\DoctorTimeSlotResource;
use App\Modules\Scheduling\Services\DoctorTimeSlotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;


class AvailabilityController extends BaseController
{
    public function __construct(
        private readonly DoctorTimeSlotService $slots
    ) {}

    public function index(AvailabilityQueryRequest $request, int $doctorId): JsonResponse
    {
        $doctor = Doctor::find($doctorId);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found.');
        }

        $data = $request->validated();

        $from = isset($data['date_from']) ? Carbon::parse($data['date_from']) : now();
        $to   = isset($data['date_to']) ? Carbon::parse($data['date_to']) : now()->addDays(14);

        $slots = $this->slots->availability($doctor, $data['clinic_id'] ?? null, $from, $to);

        return $this->successResponse(
            DoctorTimeSlotResource::collection($slots),
            'Available time slots retrieved successfully.'
        );
    }
}
