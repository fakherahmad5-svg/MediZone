<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\VerifiesDoctorClinicLink;
use App\Jobs\GenerateDoctorSlotsJob;
use App\Models\BlockedTime;
use App\Models\Doctor;
use App\Modules\Scheduling\Requests\GenerateSlotsRequest;
use App\Modules\Scheduling\Requests\SetWeeklyScheduleRequest;
use App\Modules\Scheduling\Requests\StoreBlockedTimeRequest;
use App\Modules\Scheduling\Resources\BlockedTimeResource;
use App\Modules\Scheduling\Resources\ScheduleConfigResource;
use App\Modules\Scheduling\Services\BlockedTimeService;
use App\Modules\Scheduling\Services\ScheduleConfigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ReceptionistScheduleController extends BaseController
{
    use VerifiesDoctorClinicLink;

    public function __construct(
        private readonly ScheduleConfigService $schedules,
        private readonly BlockedTimeService $blockedTimes,
    ) {}

    public function show(Request $request, int $doctorId): JsonResponse
    {
        $clinicId = $this->receptionistClinicId($request);
        $doctor   = $this->doctorLinkedToClinic($doctorId, $clinicId);
        $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        return $this->successResponse(
            new ScheduleConfigResource($this->schedules->forDoctorClinic($doctor, $clinicId)),
            'Schedule retrieved successfully.'
        );
    }
    public function setWeekly(SetWeeklyScheduleRequest $request, int $doctorId): JsonResponse
    {
        $clinicId = $this->receptionistClinicId($request);
        $doctor   = $this->doctorLinkedToClinic($doctorId, $clinicId);
        $clinic   = $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $config = $this->schedules->setWeeklySchedule($doctor, $clinic, $request->validated());

        return $this->successResponse(
            new ScheduleConfigResource($config),
            'Weekly schedule saved successfully.'
        );
    }



    public function generateSlots(GenerateSlotsRequest $request, int $doctorId): JsonResponse
    {
        $clinicId = $this->receptionistClinicId($request);
        $doctor   = $this->doctorLinkedToClinic($doctorId, $clinicId);
        $clinic   = $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $from = Carbon::parse($request->validated('date_from'));
        $to   = Carbon::parse($request->validated('date_to'));

        GenerateDoctorSlotsJob::dispatchSync($doctor, $clinic, $from, $to);

        return $this->successResponse(message: 'Time slots generated successfully.');
    }

    public function listBlockedTimes(Request $request, int $doctorId): JsonResponse
    {
        $clinicId = $this->receptionistClinicId($request);
        $doctor   = $this->doctorLinkedToClinic($doctorId, $clinicId);

        return $this->successResponse(
            BlockedTimeResource::collection($this->blockedTimes->listForDoctorClinic($doctor, $clinicId)),
            'Blocked times retrieved successfully.'
        );
    }

    public function storeBlockedTime(StoreBlockedTimeRequest $request, int $doctorId): JsonResponse
    {
        $clinicId = $this->receptionistClinicId($request);
        $doctor   = $this->doctorLinkedToClinic($doctorId, $clinicId);
        $clinic   = $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $result = $this->blockedTimes->create($doctor, $clinic, $request->validated());

        return $this->createdResponse(
            [
                'blocked_time'              => new BlockedTimeResource($result['blocked_time']),
                'affected_available_slots' => $result['affected_available_slots'],
                'affected_bookings'         => $result['affected_bookings'],
            ],
            'Blocked time created successfully.'
        );
    }

    public function destroyBlockedTime(Request $request, int $id): JsonResponse
    {
        $clinicId    = $this->receptionistClinicId($request);
        $blockedTime = BlockedTime::findOrFail($id);

        // موظف عيادة A لا يستطيع حذف حجب يخص عيادة B
        if ($blockedTime->clinic_id !== $clinicId) {
            throw new NotFoundException('Blocked time not found.');
        }

        $doctor = Doctor::findOrFail($blockedTime->doctor_id);
        $this->blockedTimes->delete($doctor, $blockedTime);

        return $this->noContentResponse();
    }


    private function receptionistClinicId(Request $request): int
    {
        $clinicId = $request->user()->clinicUsers()->value('clinic_id');

        if (! $clinicId) {
            throw new AuthorizationException('Your account is not linked to any clinic.');
        }

        return $clinicId;
    }


    private function doctorLinkedToClinic(int $doctorId, int $clinicId): Doctor
    {
        $doctor = Doctor::find($doctorId);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found.');
        }


        return $doctor;
    }
}
