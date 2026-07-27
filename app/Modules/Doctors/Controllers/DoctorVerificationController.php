<?php

namespace App\Modules\Doctors\Controllers;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Doctors\Requests\RejectDoctorRequest;
use App\Modules\Doctors\Requests\SuspendDoctorRequest;
use App\Modules\Doctors\Resources\DoctorAdminResource;
use App\Modules\Doctors\Services\DoctorVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class DoctorVerificationController extends BaseController
{
    public function __construct(
        private readonly DoctorVerificationService $verification
    ) {}

    public function adminIndex(Request $request): JsonResponse
    {
        $this->authorizeManageDoctors($request);

        $filters = $request->only(['status', 'department_id']);

        return $this->paginatedResponse(
            $this->verification->paginateForAdmin($filters, paginate_per_page()),
            'Doctors retrieved successfully.',
            fn ($doctor) => (new DoctorAdminResource($doctor))->resolve($request)
        );
    }

    public function pending(Request $request): JsonResponse
    {
        $this->authorizeManageDoctors($request);

        return $this->paginatedResponse(
            $this->verification->pendingList(paginate_per_page()),
            'Pending doctors retrieved successfully.',
            fn ($doctor) => (new DoctorAdminResource($doctor))->resolve($request)
        );
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageDoctors($request);

        $doctor = $this->verification->approve(
            $this->verification->findOrFail($id),
            $request->user()
        );

        return $this->successResponse(
            new DoctorAdminResource($doctor),
            'Doctor verified successfully.'
        );
    }

    public function reject(RejectDoctorRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageDoctors($request);

        $doctor = $this->verification->reject(
            $this->verification->findOrFail($id),
            $request->user(),
            $request->validated('reason')
        );

        return $this->successResponse(
            new DoctorAdminResource($doctor),
            'Doctor rejected.'
        );
    }

    public function suspend(SuspendDoctorRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageDoctors($request);

        $doctor = $this->verification->suspend(
            $this->verification->findOrFail($id),
            $request->user(),
            $request->validated('reason')
        );

        return $this->successResponse(
            new DoctorAdminResource($doctor),
            'Doctor suspended.'
        );
    }

    public function reactivate(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageDoctors($request);

        $doctor = $this->verification->reactivate(
            $this->verification->findOrFail($id),
            $request->user()
        );

        return $this->successResponse(
            new DoctorAdminResource($doctor),
            'Doctor reactivated.'
        );
    }

    private function authorizeManageDoctors(Request $request): void
    {
        if (! $request->user()?->hasPermission('doctors.manage')) {
            throw new AuthorizationException('You do not have permission to manage doctors.');
        }
    }
}
