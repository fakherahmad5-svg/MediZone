<?php

namespace App\Modules\Payments\Services;

use App\Core\Enums\TopUpRequestStatus;
use App\Core\Enums\WalletTransactionType;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\TopUpRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * طلبات شحن رصيد المريض - المريض بيطلب مبلغ، الأدمن بيوافق (فبيتشحن
 * فعلياً عبر WalletService) أو يرفض (بدون أي حركة على المحفظة). ما في
 * بوابة دفع خارجية هون بقصد - الشحن عملياً بيصير "يدوي" (تحويل/كاش
 * للأدمن مباشرة) وبعدين الأدمن بسجل الموافقة هون.
 */
class TopUpService extends BaseService
{
    public function __construct(private readonly WalletService $wallets) {}

    public function request(User $patientUser, float $amount): TopUpRequest
    {
        if ($amount <= 0) {
            throw new BusinessException('Top-up amount must be greater than zero.');
        }

        return TopUpRequest::create([
            'user_id' => $patientUser->id,
            'amount'  => round($amount, 2),
            'status'  => TopUpRequestStatus::Pending->value,
        ]);
    }

    public function forUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return TopUpRequest::where('user_id', $user->id)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest('id')
            ->paginate($perPage);
    }

    public function all(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return TopUpRequest::with('user:id,first_name,last_name')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->latest('id')
            ->paginate($perPage);
    }

    public function approve(TopUpRequest $request, User $admin): TopUpRequest
    {
        if ($request->status !== TopUpRequestStatus::Pending) {
            throw new BusinessException('Only pending requests can be approved.');
        }

        return $this->transaction(function () use ($request, $admin) {
            $wallet = $this->wallets->walletFor($request->user);

            $this->wallets->credit(
                $wallet, (float) $request->amount, WalletTransactionType::TopUp,
                $request, "Top-up request #{$request->id} approved", $admin
            );

            $request->update([
                'status'       => TopUpRequestStatus::Approved->value,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    public function reject(TopUpRequest $request, User $admin, ?string $note = null): TopUpRequest
    {
        if ($request->status !== TopUpRequestStatus::Pending) {
            throw new BusinessException('Only pending requests can be rejected.');
        }

        $request->update([
            'status'       => TopUpRequestStatus::Rejected->value,
            'admin_note'   => $note,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        return $request->fresh();
    }

    public function findOrFail(int $id): TopUpRequest
    {
        $request = TopUpRequest::find($id);

        if (! $request) {
            throw new NotFoundException('Top-up request not found.');
        }

        return $request;
    }
}
