<?php

namespace App\Modules\Receptionists\Controllers;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Receptionists\Requests\RejectReceptionistRequest;
use App\Modules\Receptionists\Requests\SuspendReceptionistRequest;
use App\Modules\Receptionists\Resources\ReceptionistAdminResource;
use App\Modules\Receptionists\Services\ReceptionistVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ReceptionistVerificationController extends BaseController
{
    public function __construct(
        private readonly ReceptionistVerificationService $verification
    ) {}

    public function adminIndex(Request $request): JsonResponse
    {
        $this->authorizeManageReceptionists($request);

        $filters = $request->only(['status', 'clinic_id']);

        return $this->paginatedResponse(
            $this->verification->paginateForAdmin($filters, paginate_per_page()),
            'Receptionists retrieved successfully.',
            fn ($r) => (new ReceptionistAdminResource($r))->resolve($request)
        );
    }

    public function pending(Request $request): JsonResponse
    {
        $this->authorizeManageReceptionists($request);

        return $this->paginatedResponse(
            $this->verification->pendingList(paginate_per_page()),
            'Pending receptionists retrieved successfully.',
            fn ($r) => (new ReceptionistAdminResource($r))->resolve($request)
        );
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageReceptionists($request);

        $receptionist = $this->verification->approve(
            $this->verification->findOrFail($id),
            $request->user()
        );

        return $this->successResponse(
            new ReceptionistAdminResource($receptionist),
            'Receptionist approved successfully.'
        );
    }

    public function reject(RejectReceptionistRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageReceptionists($request);

        $receptionist = $this->verification->reject(
            $this->verification->findOrFail($id),
            $request->user(),
            $request->validated('reason')
        );

        return $this->successResponse(
            new ReceptionistAdminResource($receptionist),
            'Receptionist rejected.'
        );
    }

    public function suspend(SuspendReceptionistRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageReceptionists($request);

        $receptionist = $this->verification->suspend(
            $this->verification->findOrFail($id),
            $request->user(),
            $request->validated('reason')
        );

        return $this->successResponse(
            new ReceptionistAdminResource($receptionist),
            'Receptionist suspended.'
        );
    }

    public function reactivate(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageReceptionists($request);

        $receptionist = $this->verification->reactivate(
            $this->verification->findOrFail($id),
            $request->user()
        );

        return $this->successResponse(
            new ReceptionistAdminResource($receptionist),
            'Receptionist reactivated.'
        );
    }

    private function authorizeManageReceptionists(Request $request): void
    {
        if (! $request->user()?->hasPermission('manage_receptionists')) {
            throw new AuthorizationException('You do not have permission to manage receptionists.');
        }
    }
}
