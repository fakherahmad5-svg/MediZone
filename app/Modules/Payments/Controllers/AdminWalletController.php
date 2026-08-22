<?php

namespace App\Modules\Payments\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Payments\Requests\ProcessWalletRequestRequest;
use App\Modules\Payments\Resources\TopUpRequestResource;
use App\Modules\Payments\Resources\WalletResource;
use App\Modules\Payments\Resources\WalletTransactionResource;
use App\Modules\Payments\Resources\WithdrawalRequestResource;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\TopUpService;
use App\Modules\Payments\Services\WalletService;
use App\Modules\Payments\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdminWalletController
 *
 * Routes [auth:sanctum, role:admin]:
 *   GET  /admin/wallet/summary                               → summary()
 *   GET  /admin/wallet/platform                             → platform()
 *   GET  /admin/wallet/platform/transactions                 → platformTransactions()
 *   GET  /admin/wallet/top-up-requests                       → topUpRequests()
 *   POST /admin/wallet/top-up-requests/{id}/approve          → approveTopUp()
 *   POST /admin/wallet/top-up-requests/{id}/reject           → rejectTopUp()
 *   GET  /admin/wallet/withdrawal-requests                   → withdrawalRequests()
 *   POST /admin/wallet/withdrawal-requests/{id}/approve      → approveWithdrawal()
 *   POST /admin/wallet/withdrawal-requests/{id}/reject       → rejectWithdrawal()
 */
class AdminWalletController extends BaseController
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly TopUpService $topUps,
        private readonly WithdrawalService $withdrawals,
        private readonly PaymentService $payments,
    ) {}

    public function summary(): JsonResponse
    {
        return $this->successResponse(
            $this->payments->financialSummary(),
            'Financial summary retrieved successfully.'
        );
    }

    public function platform(): JsonResponse
    {
        return $this->successResponse(
            new WalletResource($this->wallets->platformWallet()),
            'Platform wallet retrieved successfully.'
        );
    }

    public function platformTransactions(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'from', 'to']);

        return $this->paginatedResponse(
            $this->wallets->transactionsFor($this->wallets->platformWallet(), $filters, paginate_per_page()),
            'Platform transactions retrieved successfully.',
            fn ($t) => (new WalletTransactionResource($t))->resolve($request)
        );
    }

    public function topUpRequests(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'user_id']);

        return $this->paginatedResponse(
            $this->topUps->all($filters, paginate_per_page()),
            'Top-up requests retrieved successfully.',
            fn ($r) => (new TopUpRequestResource($r))->resolve($request)
        );
    }

    public function approveTopUp(Request $request, int $id): JsonResponse
    {
        $topUp = $this->topUps->findOrFail($id);
        $updated = $this->topUps->approve($topUp, $request->user());

        return $this->successResponse(new TopUpRequestResource($updated), 'Top-up request approved and wallet credited.');
    }

    public function rejectTopUp(ProcessWalletRequestRequest $request, int $id): JsonResponse
    {
        $topUp = $this->topUps->findOrFail($id);
        $updated = $this->topUps->reject($topUp, $request->user(), $request->validated('admin_note'));

        return $this->successResponse(new TopUpRequestResource($updated), 'Top-up request rejected.');
    }

    public function withdrawalRequests(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'user_id']);

        return $this->paginatedResponse(
            $this->withdrawals->all($filters, paginate_per_page()),
            'Withdrawal requests retrieved successfully.',
            fn ($r) => (new WithdrawalRequestResource($r))->resolve($request)
        );
    }

    public function approveWithdrawal(Request $request, int $id): JsonResponse
    {
        $withdrawal = $this->withdrawals->findOrFail($id);
        $updated = $this->withdrawals->approve($withdrawal, $request->user());

        return $this->successResponse(new WithdrawalRequestResource($updated), 'Withdrawal request approved and wallet debited.');
    }

    public function rejectWithdrawal(ProcessWalletRequestRequest $request, int $id): JsonResponse
    {
        $withdrawal = $this->withdrawals->findOrFail($id);
        $updated = $this->withdrawals->reject($withdrawal, $request->user(), $request->validated('admin_note'));

        return $this->successResponse(new WithdrawalRequestResource($updated), 'Withdrawal request rejected.');
    }
}
