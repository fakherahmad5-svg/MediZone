<?php

namespace App\Modules\Doctors\Controllers;

use App\Core\Enums\StripeAccountType;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\StripeConnectAccountException;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Payments\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    // Already fully connected and ready — regardless of account type
    // (Express or Standard) — don't call Stripe again.
    if ($doctor->stripe_connect_id && $doctor->stripe_active) {
        return $this->successResponse(
            [
                'already_connected' => true,
                'stripe_account_type' => $doctor->stripe_account_type?->value,
                'stripe_active' => $doctor->stripe_active,
            ],
            'Doctor is already connected to Stripe.'
        );
    }

    // A Standard account (linked via OAuth) never goes through Express
    // onboarding, even if not marked active yet — use connect-existing
    // or refresh-status instead.
    if ($doctor->stripe_account_type === StripeAccountType::Standard) {
        return $this->successResponse(
            [
                'already_connected' => true,
                'stripe_account_type' => $doctor->stripe_account_type->value,
                'stripe_active' => $doctor->stripe_active,
            ],
            'Doctor is connected via an existing Stripe account. Use refresh-status to check readiness.'
        );
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

    /**
     * Case 2 — the doctor already owns a Stripe account and wants to
     * connect it, instead of us creating a new Express account.
     */
    public function connectExisting(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        if ($doctor->stripe_connect_id) {
            return $this->successResponse(
                [
                    'already_connected' => true,
                    'stripe_account_type' => $doctor->stripe_account_type?->value,
                    'stripe_active' => $doctor->stripe_active,
                ],
                'Doctor already has a connected Stripe account.'
            );
        }

        $authorizeUrl = $this->stripe->buildOAuthAuthorizeUrl(
            $doctor,
            route('api.v1.doctor.stripe.oauth.callback')
        );

        return $this->successResponse(
            ['authorize_url' => $authorizeUrl],
            'Stripe OAuth authorization link created successfully.'
        );
    }

    /**
     * Public callback — Stripe redirects the doctor's browser here
     * directly, unauthenticated, after they approve the connection.
     */
    public function oauthCallback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        $frontendReturnUrl = config('app.frontend_url') . '/doctor/stripe/return';
        $frontendRefreshUrl = config('app.frontend_url') . '/doctor/stripe/refresh';
if ($error || ! $code  || ! $state) {
    Log::warning('Stripe Connect OAuth callback missing code/state or denied.', [
        'error' => $error,
    ]);

    return redirect()->away("{$frontendRefreshUrl}?stripe_connect=denied");
}

     try {
    $this->stripe->linkExistingAccountViaOAuth($code, $state);
} catch (StripeConnectAccountException $e) {
    Log::warning('Stripe Connect OAuth linking failed.', [
        'message' => $e->getMessage(),
    ]);

    return redirect()->away("{$frontendRefreshUrl}?stripe_connect=failed");
}
        
return redirect()->away("{$frontendReturnUrl}?stripe_connect=success");
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