<?php

namespace App\Modules\Payments\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Payments\Requests\CreateTopUpRequestRequest;
use App\Modules\Payments\Resources\TopUpRequestResource;
use App\Modules\Payments\Resources\WalletResource;
use App\Modules\Payments\Resources\WalletTransactionResource;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\TopUpService;
use App\Modules\Payments\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PatientWalletController
 *
 * Routes [auth:sanctum, role:patient]:
 *   GET  /patient/wallet                       → show()
 *   GET  /patient/wallet/transactions           → transactions()
 *   GET  /patient/wallet/payment-options        → paymentOptions()  (deposit %/allowed methods, before booking)
 *   POST /patient/wallet/top-up-requests        → storeTopUpRequest()
 *   GET  /patient/wallet/top-up-requests        → topUpRequests()
 */
class PatientWalletController extends BaseController
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly TopUpService $topUps,
        private readonly PaymentService $payments,
    ) {}

    /**
     * خيارات الدفع المتاحة للمريض الحالي (مستقلة عن أي موعد بعينه) -
     * تُستخدم بشاشة الحجز نفسها قبل ما يختار الموعد/الطريقة، حتى يعرف
     * مسبقاً هل الكاش+عربون متاح إله وقديش نسبة العربون.
     */
    public function paymentOptions(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $this->successResponse(
            $this->payments->paymentOptions($patient),
            'Payment options retrieved successfully.'
        );
    }

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->wallets->walletFor($request->user());

        return $this->successResponse(new WalletResource($wallet), 'Wallet retrieved successfully.');
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->wallets->walletFor($request->user());
        $filters = $request->only(['type', 'from', 'to']);

        return $this->paginatedResponse(
            $this->wallets->transactionsFor($wallet, $filters, paginate_per_page()),
            'Transactions retrieved successfully.',
            fn ($t) => (new WalletTransactionResource($t))->resolve($request)
        );
    }

    public function storeTopUpRequest(CreateTopUpRequestRequest $request): JsonResponse
    {
        $topUp = $this->topUps->request($request->user(), (float) $request->validated('amount'));

        return $this->createdResponse(
            new TopUpRequestResource($topUp),
            'Top-up request submitted. An administrator will review it shortly.'
        );
    }

    public function topUpRequests(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);

        return $this->paginatedResponse(
            $this->topUps->forUser($request->user(), $filters, paginate_per_page()),
            'Top-up requests retrieved successfully.',
            fn ($r) => (new TopUpRequestResource($r))->resolve($request)
        );
    }
}
