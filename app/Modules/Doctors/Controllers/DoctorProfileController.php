<?php

namespace App\Modules\Doctors\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Doctors\Requests\CreateClinicRequest;
use App\Modules\Doctors\Requests\LeaveDepartmentRequest;
use App\Modules\Doctors\Requests\UpdateDoctorProfileRequest;
use App\Modules\Doctors\Requests\UploadDoctorCertificateRequest;
use App\Modules\Doctors\Requests\UploadDoctorPhotoRequest;
use App\Modules\Doctors\Resources\DoctorSelfResource;
use App\Modules\Doctors\Services\DoctorProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class DoctorProfileController extends BaseController
{
    public function __construct(
        private readonly DoctorProfileService $profiles
    ) {}

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new DoctorSelfResource($this->profiles->forUser($request->user())),
            'Doctor profile retrieved successfully.'
        );
    }

    public function update(UpdateDoctorProfileRequest $request): JsonResponse
    {
        $doctor = $this->profiles->forUser($request->user());

        $this->profiles->updateProfile($doctor, $request->validated());

        return $this->successResponse(
            new DoctorSelfResource($doctor->fresh(['user','profile', 'departments', 'clinics'])),
            'Profile updated successfully.'
        );
    }

    public function uploadPhoto(UploadDoctorPhotoRequest $request): JsonResponse
    {
        $doctor = $this->profiles->forUser($request->user());

        $this->profiles->updatePhoto($doctor, $request->file('photo'));

        return $this->successResponse(
            new DoctorSelfResource($doctor->fresh(['user','profile', 'departments', 'clinics'])),
            'Photo updated successfully.'
        );
    }

    public function uploadCertificate(UploadDoctorCertificateRequest $request): JsonResponse
    {
        $doctor = $this->profiles->forUser($request->user());

        $this->profiles->addCertificate($doctor, $request->file('certificate'));

        return $this->successResponse(
            new DoctorSelfResource($doctor->fresh(['user','profile', 'departments', 'clinics'])),
            'Certificate uploaded successfully.'
        );
    }

    public function joinClinic(JoinClinicRequest $request): JsonResponse
    {
        $doctor = $this->profiles->forUser($request->user());
        $data   = $request->validated();

        $this->profiles->joinClinic($doctor, $data['clinic_id'], $data['department_ids']);

        return $this->successResponse(
            new DoctorSelfResource($doctor->fresh(['profile', 'departments', 'clinics'])),
            'Joined clinic successfully.'
        );
    }

    public function createClinic(CreateClinicRequest $request): JsonResponse
    {
        $doctor = $this->profiles->forUser($request->user());

        $this->profiles->createClinic($doctor, $request->validated());

        return $this->successResponse(
            new DoctorSelfResource($doctor->fresh(['user', 'profile', 'departments', 'clinics'])),
            'Clinic created successfully and is pending admin approval.'
        );
    }

    public function leaveDepartment(LeaveDepartmentRequest $request): JsonResponse
    {
        $doctor = $this->profiles->forUser($request->user());
        $data   = $request->validated();

        $this->profiles->leaveDepartment($doctor, $data['clinic_id'], $data['department_id']);

        return $this->successResponse(
            new DoctorSelfResource($doctor->fresh(['user','profile', 'departments', 'clinics'])),
            'Left department successfully.'
        );
    }
}
