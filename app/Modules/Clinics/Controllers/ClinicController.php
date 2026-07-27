<?php

namespace App\Modules\Clinics\Controllers;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Clinics\Requests\RejectClinicRequest;
use App\Modules\Clinics\Requests\StoreClinicRequest;
use App\Modules\Clinics\Requests\SuspendClinicRequest;
use App\Modules\Clinics\Requests\SyncClinicDepartmentsRequest;
use App\Modules\Clinics\Requests\UpdateClinicRequest;
use App\Modules\Clinics\Resources\ClinicAdminResource;
use App\Modules\Clinics\Resources\ClinicResource;
use App\Modules\Clinics\Services\ClinicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ClinicController extends BaseController
{
    public function __construct(
        private readonly ClinicService $clinics
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  Public
    // ─────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $clinics = $this->clinics->activeList($request->query('search'));

        return $this->successResponse(
            ClinicResource::collection($clinics->load('departments')),
            'Clinics retrieved successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $clinic = $this->clinics->findActiveOrFail($id)->load('departments');

        return $this->successResponse(
            new ClinicResource($clinic),
            'Clinic retrieved successfully.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Admin — Management
    // ─────────────────────────────────────────────────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        $this->authorizeManageClinics($request);

        $filters = $request->only(['status', 'search']);

        return $this->paginatedResponse(
            $this->clinics->paginateForAdmin($filters, paginate_per_page()),
            'Clinics retrieved successfully.',
            fn ($clinic) => (new ClinicAdminResource($clinic))->resolve($request)
        );
    }

    public function pending(Request $request): JsonResponse
    {
        $this->authorizeManageClinics($request);

        return $this->paginatedResponse(
            $this->clinics->pendingList(paginate_per_page()),
            'Pending clinics retrieved successfully.',
            fn ($clinic) => (new ClinicAdminResource($clinic))->resolve($request)
        );
    }

    public function store(StoreClinicRequest $request): JsonResponse
    {
        $this->authorizeManageClinics($request);

        $clinic = $this->clinics->createByAdmin($request->validated());

        return $this->createdResponse(
            new ClinicAdminResource($clinic),
            'Clinic created successfully.'
        );
    }

    public function update(UpdateClinicRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageClinics($request);
        $clinic = $this->clinics->update(
            $this->clinics->findOrFail($id),
            $request->validated()
        );

        return $this->successResponse(
            new ClinicAdminResource($clinic),
            'Clinic updated successfully.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Admin — Approval Workflow
    // ─────────────────────────────────────────────────────────────

    public function approve(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageClinics($request);

        $clinic = $this->clinics->approve(
            $this->clinics->findOrFail($id),
            $request->user()
        );

        return $this->successResponse(
            new ClinicAdminResource($clinic),
            'Clinic approved successfully.'
        );
    }

    public function reject(RejectClinicRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageClinics($request);

        $clinic = $this->clinics->reject(
            $this->clinics->findOrFail($id),
            $request->user(),
            $request->validated('reason')
        );

        return $this->successResponse(
            new ClinicAdminResource($clinic),
            'Clinic rejected.'
        );
    }

    public function suspend(SuspendClinicRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageClinics($request);

        $clinic = $this->clinics->suspend(
            $this->clinics->findOrFail($id),
            $request->user(),
            $request->validated('reason')
        );

        return $this->successResponse(
            new ClinicAdminResource($clinic),
            'Clinic suspended.'
        );
    }

    public function reactivate(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageClinics($request);

        $clinic = $this->clinics->reactivate(
            $this->clinics->findOrFail($id),
            $request->user()
        );

        return $this->successResponse(
            new ClinicAdminResource($clinic),
            'Clinic reactivated.'
        );
    }

    public function syncDepartments(SyncClinicDepartmentsRequest $request, int $id): JsonResponse
    {
        $this->authorizeManageClinics($request);

        $clinic = $this->clinics->syncDepartments(
            $this->clinics->findOrFail($id),
            $request->validated('department_ids')
        );

        return $this->successResponse(
            new ClinicAdminResource($clinic),
            'Clinic departments updated successfully.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────────────────────

    private function authorizeManageClinics(Request $request): void
    {
        if (! $request->user()?->hasPermission('clinics.manage')) {
            throw new AuthorizationException('You do not have permission to manage clinics.');
        }
    }
}
