<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasUuids;

    protected $fillable = ['role_name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isAdministratorSistem(): bool
    {
        return $this->role_name === 'Administrator Sistem';
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
