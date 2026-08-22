<?php

namespace App\Modules\Payments\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Payments\Requests\CreateWithdrawalRequestRequest;
use App\Modules\Payments\Resources\WalletResource;
use App\Modules\Payments\Resources\WalletTransactionResource;
use App\Modules\Payments\Resources\WithdrawalRequestResource;
use App\Modules\Payments\Services\WalletService;
use App\Modules\Payments\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DoctorWalletController
 *
 * Routes [auth:sanctum, role:doctor]:
 *   GET  /doctor/wallet                          → show()
 *   GET  /doctor/wallet/transactions               → transactions()
 *   POST /doctor/wallet/withdrawal-requests        → storeWithdrawalRequest()
 *   GET  /doctor/wallet/withdrawal-requests        → withdrawalRequests()
 */
class DoctorWalletController extends BaseController
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly WithdrawalService $withdrawals,
    ) {}

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

    public function storeWithdrawalRequest(CreateWithdrawalRequestRequest $request): JsonResponse
    {
        $withdrawal = $this->withdrawals->request($request->user(), (float) $request->validated('amount'));

        return $this->createdResponse(
            new WithdrawalRequestResource($withdrawal),
            'Withdrawal request submitted. An administrator will review it shortly.'
        );
    }

    public function withdrawalRequests(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);

        return $this->paginatedResponse(
            $this->withdrawals->forUser($request->user(), $filters, paginate_per_page()),
            'Withdrawal requests retrieved successfully.',
            fn ($r) => (new WithdrawalRequestResource($r))->resolve($request)
        );
    }
}
