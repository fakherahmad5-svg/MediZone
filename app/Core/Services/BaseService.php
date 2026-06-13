<?php

namespace App\Core\Services;

use App\Core\Traits\HasAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    use HasAuditLog;

    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    protected function logError(
        string $context,
        \Throwable $e,
        array $extra = []
    ): void {
        Log::error("[{$context}] {$e->getMessage()}", array_merge([
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ], $extra));
    }
}
