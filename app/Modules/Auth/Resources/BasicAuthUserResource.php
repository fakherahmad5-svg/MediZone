<?php


namespace App\Modules\Auth\Resources;

use App\Core\Enums\UserRole;
use App\Core\Http\Resources\BaseResource;
use App\Models\User;
use App\Modules\Auth\Services\UserRoleService;

/** @mixin User */
class BasicAuthUserResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        $roleService = app(UserRoleService::class);
        $role = $roleService->getRole($this->resource);

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'role' => $role,
            'role_label' => UserRole::tryFrom((string)$role)?->label(),
            'email_verified' => $this->hasVerifiedEmail(),
        ];
    }
}
