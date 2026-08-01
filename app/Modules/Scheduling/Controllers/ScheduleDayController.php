<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\ScheduleConfig;
use App\Modules\Scheduling\Requests\StoreScheduleDayRequest;
use App\Modules\Scheduling\Services\ScheduleDayService;

class ScheduleDayController extends BaseController
{
    public function __construct(private ScheduleDayService $service) {}

    public function store(StoreScheduleDayRequest $request, ScheduleConfig $config)
    {
        $result = $this->service->storeDays($config, $request->validated());
        return $this->successResponse($result, 'Schedule days saved successfully.', 201);
    }

    public function index(ScheduleConfig $config)
    {
        $result = $this->service->getDays($config);
        return $this->successResponse($result, 'Schedule days retrieved successfully.');
    }
}
