<?php

namespace App\Modules\Departments\Controllers;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\Controllers\BaseController;
use App\Models\Department;
use App\Modules\Departments\Requests\StoreDepartmentRequest;
use App\Modules\Departments\Requests\UpdateDepartmentRequest;
use App\Modules\Departments\Resources\DepartmentResource;
use App\Modules\Departments\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class DepartmentController extends BaseController
{
    public function __construct(
        private readonly DepartmentService $departments
    ) {}


    public function index(): JsonResponse
    {
        return $this->successResponse(
            DepartmentResource::collection($this->departments->all()),
            'Departments retrieved successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->successResponse(
            new DepartmentResource($this->departments->findOrFail($id)),
            'Department retrieved successfully.'
        );
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $this->authorizeManageDepartments($request);

        return $this->paginatedResponse(
            $this->departments->paginate(paginate_per_page()),
            'Departments retrieved successfully.'
        );
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorizeManageDepartments($request);

        $department = $this->departments->create($request->validated());

        return $this->createdResponse(
            new DepartmentResource($department),
            'Department created successfully.'
        );
    }

    public function update(UpdateDepartmentRequest $request, int $id): JsonResponse
    {dd($request->validated());
        $this->authorizeManageDepartments($request);

        $department = $this->departments->update(
            $this->departments->findOrFail($id),
            $request->validated()
        );


        return $this->successResponse(
            new DepartmentResource($department),
            'Department updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageDepartments($request);

        $this->departments->delete($this->departments->findOrFail($id));

        return $this->noContentResponse();
    }


    private function authorizeManageDepartments(Request $request): void
    {
        if (! $request->user()?->hasPermission('departments.manage')) {
            throw new AuthorizationException('You do not have permission to manage departments.');
        }
    }
}
