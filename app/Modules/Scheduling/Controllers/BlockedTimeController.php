<?php

namespace App\Modules\Scheduling\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\BlockedTime;
use App\Modules\Scheduling\Requests\StoreBlockedTimeRequest;
use App\Modules\Scheduling\Services\BlockedTimeService;

class BlockedTimeController extends BaseController
{
    public function __construct(private BlockedTimeService $service) {}

    public function store(StoreBlockedTimeRequest $request)
    {
        $blocked = $this->service->store($request->validated());
        return $this->createdResponse($blocked, 'Blocked time created successfully.');
    }

    public function index()
    {
        $blocked = $this->service->getForDoctor();
        return $this->successResponse($blocked, 'Blocked times retrieved successfully.');
    }

  public function destroy(BlockedTime $blockedTime)
{
    $this->service->delete($blockedTime);
    return $this->successResponse(null, 'Blocked time deleted successfully.');
}
}
