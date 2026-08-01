<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\ScheduleConfig;
use App\Modules\Scheduling\Jobs\GenerateDoctorSlotsJob;
use App\Modules\Scheduling\Requests\StoreScheduleConfigRequest;
use App\Modules\Scheduling\Requests\UpdateScheduleConfigRequest;
use App\Modules\Scheduling\Services\ScheduleConfigService;

class ScheduleConfigController extends BaseController
{
    public function __construct(private ScheduleConfigService $service) {}

    public function store(StoreScheduleConfigRequest $request)
    {
        $config = $this->service->store($request->validated());
        return $this->createdResponse($config, 'Schedule config created successfully.');
    }

    public function index()
    {
        $configs = $this->service->getForDoctor();
        return $this->successResponse($configs, 'Schedule configs retrieved successfully.');
    }

    public function update(UpdateScheduleConfigRequest $request, ScheduleConfig $config)
    {
        $result = $this->service->update($config, $request->validated());
        return $this->successResponse($result, 'Schedule config updated successfully.');
    }

    public function toggleVacation(ScheduleConfig $config)
    {
        $result = $this->service->toggleVacation($config);
        $status = $result->is_vacation_mode ? 'enabled' : 'disabled';
        return $this->successResponse($result, "Vacation mode {$status}.");
    }

    public function generateSlots(ScheduleConfig $config)
    {
        GenerateDoctorSlotsJob::dispatch($config, 14);
        return $this->successResponse(null, 'Slot generation started for the next 14 days.');
    }
}
