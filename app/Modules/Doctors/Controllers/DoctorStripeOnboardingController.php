<?php

namespace App\Modules\Doctors\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Exceptions\NotFoundException;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorStripeOnboardingController extends BaseController
{
    public function __construct(
        private readonly StripePaymentService $stripe,
    ) {}

    public function createLink(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        $link = $this->stripe->createOnboardingLink(
            $doctor,
            config('app.frontend_url') . '/doctor/stripe/refresh',
            config('app.frontend_url') . '/doctor/stripe/return'
        );

        return $this->successResponse(
            ['onboarding_url' => $link->url],
            'Onboarding link created successfully.'
        );
    }

    public function refreshStatus(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        $status = $this->stripe->refreshAccountStatus($doctor);

        return $this->successResponse($status, 'Stripe account status refreshed.');
    }
}