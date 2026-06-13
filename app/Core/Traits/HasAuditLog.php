<?php

namespace App\Core\Traits;


use Illuminate\Support\Facades\Log;

trait HasAuditLog
{

    protected function logAuditAction(
        string $action,
        string $entityType,
        int $entityId,
        array $oldValues = [],
        array $newValues = []
    ): void {

        Log::channel('audit')->info('[AUDIT]', [
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'user_id'     => auth()->id(),
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'timestamp'   => now()->toIso8601String(),
        ]);
    }
}
