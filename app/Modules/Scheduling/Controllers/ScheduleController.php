<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\VerifiesDoctorClinicLink;
use App\Jobs\GenerateDoctorSlotsJob;
use App\Modules\Scheduling\Requests\GenerateSlotsRequest;
use App\Modules\Scheduling\Requests\SetVacationRequest;
use App\Modules\Scheduling\Requests\SetWeeklyScheduleRequest;
use App\Modules\Scheduling\Resources\ScheduleConfigResource;
use App\Modules\Scheduling\Services\ScheduleConfigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


class ScheduleController extends BaseController
{
    use VerifiesDoctorClinicLink;

    public function __construct(
        private readonly ScheduleConfigService $schedules
    ) {}


    public function myClinics(Request $request): JsonResponse
    {
        $doctor = $this->doctorFor($request);

        return $this->successResponse(
            ScheduleConfigResource::collection($this->schedules->allForDoctor($doctor)),
            'Schedules retrieved successfully.'
        );
    }

    public function show(Request $request, int $clinicId): JsonResponse
    {
        $doctor = $this->doctorFor($request);
        $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        return $this->successResponse(
            new ScheduleConfigResource($this->schedules->forDoctorClinic($doctor, $clinicId)),
            'Schedule retrieved successfully.'
        );
    }

    public function setWeekly(SetWeeklyScheduleRequest $request, int $clinicId): JsonResponse
    {
        $doctor = $this->doctorFor($request);
        $clinic = $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $config = $this->schedules->setWeeklySchedule($doctor, $clinic, $request->validated());

        return $this->successResponse(
            new ScheduleConfigResource($config),
            'Weekly schedule saved successfully.'
        );
    }

    public function activateVacation(SetVacationRequest $request, int $clinicId): JsonResponse
    {
        $doctor = $this->doctorFor($request);
        $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $config = $this->schedules->forDoctorClinic($doctor, $clinicId);

        $result = $this->schedules->activateVacation(
            $config,
            Carbon::parse($request->validated('start_date')),
            Carbon::parse($request->validated('end_date'))
        );

        return $this->successResponse(
            [
                'schedule'                  => new ScheduleConfigResource($result['config']),
                'affected_available_slots' => $result['affected_available_slots'],
                'affected_bookings'         => $result['affected_bookings'],
            ],
            $result['affected_bookings'] > 0
                ? "Vacation mode activated. Warning: {$result['affected_bookings']} existing booking(s) overlap this period and require manual attention."
                : 'Vacation mode activated. No new slots will be generated for this period.'
        );
    }

    public function deactivateVacation(Request $request, int $clinicId): JsonResponse
    {
        $doctor = $this->doctorFor($request);
        $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $config  = $this->schedules->forDoctorClinic($doctor, $clinicId);
        $updated = $this->schedules->deactivateVacation($config);

        return $this->successResponse(
            new ScheduleConfigResource($updated),
            'Vacation mode disabled.'
        );
    }

    public function generateSlots(GenerateSlotsRequest $request, int $clinicId): JsonResponse
    {
        $doctor = $this->doctorFor($request);
        $clinic = $this->ensureDoctorLinkedToClinic($doctor, $clinicId);

        $from = Carbon::parse($request->validated('date_from'));
        $to   = Carbon::parse($request->validated('date_to'));

        $config  = $this->schedules->forDoctorClinic($doctor, $clinicId);

        GenerateDoctorSlotsJob::dispatchSync($doctor, $clinic, $from, $to);

        return $this->successResponse(
            message: 'Time slots generated successfully.'
        );
    }

    // ─────────────────────────────────────────────────────────────


    private function doctorFor(Request $request): \App\Models\Doctor
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        return $doctor;
    }
}
