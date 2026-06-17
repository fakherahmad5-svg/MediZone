<?php

namespace App\Modules\Auth\Data;

use App\Models\User;

class AuthResult
{
    public function __construct(
        public User $user,
        public ?string $token = null,
        public string $tokenType = 'Bearer',
        public bool $requiresVerification = false,
    ) {}

    public function hasToken(): bool
    {
        return $this->token !== null;
    }
}
