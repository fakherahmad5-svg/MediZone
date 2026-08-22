<?php

namespace App\Modules\Payments\Services;

use App\Core\Enums\WalletTransactionType;
use App\Core\Enums\WithdrawalRequestStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * طلبات سحب رصيد الطبيب - نفس فكرة TopUpService بالعكس: الطبيب بيطلب
 * سحب مبلغ من رصيده، الأدمن بيوافق (فبينخصم فعلياً من محفظته) أو يرفض.
 */
class WithdrawalService extends BaseService
{
    public function __construct(private readonly WalletService $wallets) {}

    public function request(User $doctorUser, float $amount): WithdrawalRequest
    {
        if ($amount <= 0) {
            throw new BusinessException('Withdrawal amount must be greater than zero.');
        }

        $wallet = $this->wallets->walletFor($doctorUser);

        if ($amount > $wallet->balance) {
            throw new BusinessException('Withdrawal amount exceeds your available balance.');
        }

        return WithdrawalRequest::create([
            'user_id' => $doctorUser->id,
            'amount'  => round($amount, 2),
            'status'  => WithdrawalRequestStatus::Pending->value,
        ]);
    }

    public function forUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return WithdrawalRequest::where('user_id', $user->id)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest('id')
            ->paginate($perPage);
    }

    public function all(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return WithdrawalRequest::with('user:id,first_name,last_name')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->latest('id')
            ->paginate($perPage);
    }

    public function approve(WithdrawalRequest $request, User $admin): WithdrawalRequest
    {
        if ($request->status !== WithdrawalRequestStatus::Pending) {
            throw new BusinessException('Only pending requests can be approved.');
        }

        return $this->transaction(function () use ($request, $admin) {
            $wallet = $this->wallets->walletFor($request->user);

            // إعادة التحقق من الرصيد وقت الموافقة (مش بس وقت الطلب) - ممكن
            // يكون تغيّر بالفترة بين الطلب والموافقة.
            $this->wallets->debit(
                $wallet, (float) $request->amount, WalletTransactionType::Withdrawal,
                $request, "Withdrawal request #{$request->id} approved", $admin
            );

            $request->update([
                'status'       => WithdrawalRequestStatus::Approved->value,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    public function reject(WithdrawalRequest $request, User $admin, ?string $note = null): WithdrawalRequest
    {
        if ($request->status !== WithdrawalRequestStatus::Pending) {
            throw new BusinessException('Only pending requests can be rejected.');
        }

        $request->update([
            'status'       => WithdrawalRequestStatus::Rejected->value,
            'admin_note'   => $note,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        return $request->fresh();
    }

    public function findOrFail(int $id): WithdrawalRequest
    {
        $request = WithdrawalRequest::find($id);

        if (! $request) {
            throw new NotFoundException('Withdrawal request not found.');
        }

        return $request;
    }
}
