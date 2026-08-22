<?php

namespace App\Modules\Payments\Services;

use App\Core\Enums\WalletTransactionType;
use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * WalletService - العنصر الأساسي (primitive) لكل حركة مالية بالنظام.
 * كل تعديل على رصيد أي محفظة (مريض/طبيب/المنصة) لازم يمر من هون
 * (credit/debit)، حتى نضمن إنه كل قرش إله سجل WalletTransaction مقابل،
 * وإنه القراءة/الكتابة على الرصيد محمية بـlockForUpdate ضد أي تسابق
 * (race condition) لو صارت حركتين بنفس اللحظة على نفس المحفظة.
 */
class WalletService extends BaseService
{
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['is_platform' => false, 'balance' => 0, 'currency' => 'USD']
        );
    }

    /**
     * محفظة المنصة - صف واحد وحيد (is_platform = true، user_id = null).
     * فيها بيتجمع: العربون/الدفع الكامل وقت الدفع (كأمانة)، وعمولة
     * المنصة الثابتة (٥٪) يلي بتضل فيها للأبد بعد أي تسوية.
     */
    public function platformWallet(): Wallet
    {
        return Wallet::firstOrCreate(
            ['is_platform' => true],
            ['user_id' => null, 'balance' => 0, 'currency' => 'USD']
        );
    }

    public function credit(
        Wallet $wallet,
        float $amount,
        WalletTransactionType $type,
        ?Model $related = null,
        ?string $description = null,
        ?User $actor = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new BusinessException('Credit amount must be greater than zero.');
        }

        return $this->transaction(function () use ($wallet, $amount, $type, $related, $description, $actor) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $newBalance = round($locked->balance + $amount, 2);
            $locked->update(['balance' => $newBalance]);

            return WalletTransaction::create([
                'wallet_id'     => $locked->id,
                'type'          => $type->value,
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'related_type'  => $related ? get_class($related) : null,
                'related_id'    => $related?->id,
                'description'   => $description,
                'created_by'    => $actor?->id,
            ]);
        });
    }

    public function debit(
        Wallet $wallet,
        float $amount,
        WalletTransactionType $type,
        ?Model $related = null,
        ?string $description = null,
        ?User $actor = null,
        bool $allowNegative = false
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new BusinessException('Debit amount must be greater than zero.');
        }

        return $this->transaction(function () use ($wallet, $amount, $type, $related, $description, $actor, $allowNegative) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if (! $allowNegative && $locked->balance < $amount) {
                throw new BusinessException('Insufficient wallet balance.');
            }

            $newBalance = round($locked->balance - $amount, 2);
            $locked->update(['balance' => $newBalance]);

            return WalletTransaction::create([
                'wallet_id'     => $locked->id,
                'type'          => $type->value,
                'amount'        => -$amount,
                'balance_after' => $newBalance,
                'related_type'  => $related ? get_class($related) : null,
                'related_id'    => $related?->id,
                'description'   => $description,
                'created_by'    => $actor?->id,
            ]);
        });
    }

    public function transactionsFor(Wallet $wallet, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return WalletTransaction::where('wallet_id', $wallet->id)
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('id')
            ->paginate($perPage);
    }
}
