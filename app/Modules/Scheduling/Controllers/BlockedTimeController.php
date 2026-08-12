<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\VerifiesDoctorClinicLink;
use App\Models\BlockedTime;
use App\Models\Doctor;
use App\Modules\Scheduling\Requests\StoreBlockedTimeRequest;
use App\Modules\Scheduling\Resources\BlockedTimeResource;
use App\Modules\Scheduling\Services\BlockedTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class BlockedTimeController extends BaseController
{
    use VerifiesDoctorClinicLink;

    public function __construct(
        private readonly BlockedTimeService $blockedTimes
    ) {}

    public function index(Request $request, int $clinicId): JsonResponse
    {
        $doctor = $this->doctorFor($request);
        $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        return $this->successResponse(
            BlockedTimeResource::collection($this->blockedTimes->listForDoctorClinic($doctor, $clinicId)),
            'Blocked times retrieved successfully.'
        );
    }

    public function store(StoreBlockedTimeRequest $request, int $clinicId): JsonResponse
    {
        $doctor = $this->doctorFor($request);
        $clinic = $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $result = $this->blockedTimes->create($doctor, $clinic, $request->validated());

        return $this->createdResponse(
            [
                'blocked_time'              => new BlockedTimeResource($result['blocked_time']),
                'affected_available_slots' => $result['affected_available_slots'],
                'affected_bookings'         => $result['affected_bookings'],
            ],
            $result['affected_bookings'] > 0
                ? "Blocked time created. Warning: {$result['affected_bookings']} existing booking(s) overlap this period and require manual attention."
                : 'Blocked time created successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $doctor      = $this->doctorFor($request);
        $blockedTime = BlockedTime::findOrFail($id);

        $this->blockedTimes->delete($doctor, $blockedTime);

        return $this->noContentResponse();
    }

    private function doctorFor(Request $request): Doctor
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        return $doctor;
    }
}
