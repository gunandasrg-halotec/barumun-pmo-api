<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function log(
        string $entityType,
        string $entityId,
        string $actionType,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $remarks = null,
        ?string $actionBy = null
    ): void {
        AuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action_type' => $actionType,
            'action_by' => $actionBy ?? Auth::id(),
            'action_at' => now(),
            'old_value_json' => $oldValue,
            'new_value_json' => $newValue,
            'remarks' => $remarks,
        ]);
    }

    public function logCreate(string $entityType, string $entityId, array $data): void
    {
        $this->log($entityType, $entityId, 'CREATE', null, $data);
    }

    public function logUpdate(string $entityType, string $entityId, array $old, array $new): void
    {
        $this->log($entityType, $entityId, 'UPDATE', $old, $new);
    }

    public function logApprove(string $entityType, string $entityId, ?string $remarks = null): void
    {
        $this->log($entityType, $entityId, 'APPROVE', null, null, $remarks);
    }

    public function logReject(string $entityType, string $entityId, string $reason): void
    {
        $this->log($entityType, $entityId, 'REJECT', null, null, $reason);
    }

    public function logSubmit(string $entityType, string $entityId): void
    {
        $this->log($entityType, $entityId, 'SUBMIT');
    }
}
