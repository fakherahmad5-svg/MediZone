<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('name', $permissionName);
        }

        return $this->permissions()->where('name', $permissionName)->exists();
    }
}
