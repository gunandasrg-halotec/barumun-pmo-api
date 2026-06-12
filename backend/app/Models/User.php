<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    protected $fillable = [
        'role_id',
        'full_name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role?->role_name === $roleName;
    }

    public function isAdministratorSistem(): bool
    {
        return $this->hasRole(RoleName::ADMINISTRATOR_SISTEM->value);
    }

    public function isProjectManager(): bool
    {
        return $this->hasRole(RoleName::PROJECT_MANAGER->value);
    }

    public function isDireksi(): bool
    {
        return $this->hasRole(RoleName::DIREKSI->value);
    }

    public function isFinance(): bool
    {
        return $this->hasRole(RoleName::FINANCE->value);
    }

    public function isAdminProyek(): bool
    {
        return $this->hasRole(RoleName::ADMIN_PROYEK->value);
    }

    public function canInputProgress(): bool
    {
        return $this->isProjectManager() || $this->isAdminProyek();
    }

    public function canInputCost(): bool
    {
        return $this->isFinance() || $this->isAdminProyek();
    }

    public function canApproveProgress(): bool
    {
        return $this->isProjectManager();
    }

    public function canApproveCost(): bool
    {
        return $this->isFinance();
    }

    public function canApproveWbd(): bool
    {
        return $this->isDireksi();
    }

    public function canManageWbd(): bool
    {
        return $this->isProjectManager() || $this->isAdminProyek();
    }

    public function canManageFiles(): bool
    {
        return $this->isProjectManager() || $this->isAdminProyek();
    }

    public function canGenerateReport(): bool
    {
        return $this->isProjectManager() || $this->isAdminProyek();
    }
}
