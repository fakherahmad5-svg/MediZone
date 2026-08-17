<?php

namespace App\Modules\Auth\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Modules\Auth\Notifications\VerifyEmailCodeNotification;

class EmailVerificationService extends BaseService
{
    private const CODE_LENGTH = 6;
    private const CODE_TTL_MINUTES = 10;

    public function generateAndSendCode(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new BusinessException('Email address is already verified.');
        }

        $code = $this->generateCode();
        //print ($code);
        EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        EmailVerificationCode::query()->create([
            'user_id'    => $user->id,
            'code_hash'  => $this->hashCode($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        $user->notify(new VerifyEmailCodeNotification($code));
    }

    public function verifyCode(User $user, string $code): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new BusinessException('Email address is already verified.');
        }

        $record = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record ) {
            throw new BusinessException(' expired verification code.');
        }
        if(! hash_equals($record->code_hash, $this->hashCode($code))){
            throw new BusinessException('Invalid  verification code.');
        }

        $record->update(['used_at' => now()]);

        $user->markEmailAsVerified();
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
