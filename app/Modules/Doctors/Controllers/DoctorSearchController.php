<?php

namespace App\Modules\Doctors\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Doctors\Requests\DoctorSearchRequest;
use App\Modules\Doctors\Resources\DoctorPublicResource;
use App\Modules\Doctors\Services\DoctorSearchService;
use Illuminate\Http\JsonResponse;


class DoctorSearchController extends BaseController
{
    public function __construct(
        private readonly DoctorSearchService $search
    ) {}

    public function search(DoctorSearchRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return $this->paginatedResponse(
            $this->search->search($filters, $filters['per_page'] ?? paginate_per_page()),
            'Doctors retrieved successfully.',
            fn ($doctor) => (new DoctorPublicResource($doctor))->resolve($request)
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->successResponse(
            new DoctorPublicResource($this->search->publicProfile($id)),
            'Doctor profile retrieved successfully.'
        );
    }
}
