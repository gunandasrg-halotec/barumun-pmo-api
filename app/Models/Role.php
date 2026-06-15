<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
class Role extends Model
{
    use HasUuids;

    protected $fillable = ['role_name', 'description'];

    protected $casts = [
        'role_name' => RoleName::class,
    ];
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->role_name?->name,

        );
    }
    public function isAdministratorSistem(): bool
    {
        return $this->role_name === RoleName::ADMINISTRATOR_SISTEM;
    }

    public function isProjectManager(): bool
    {
        return $this->role_name === 'Project Manager';
    }

    public function isDireksi(): bool
    {
        return $this->role_name === 'Direksi';
    }

    public function isFinance(): bool
    {
        return $this->role_name === 'Finance';
    }

    public function isAdminProyek(): bool
    {
        return $this->role_name === 'Admin Proyek';
    }
}
