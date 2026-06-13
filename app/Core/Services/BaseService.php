<?php

namespace App\Core\Services;

use App\Core\Traits\HasAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BaseService
 *
 * كل Services في المشروع ترث من هذا الـ class.
 *
 * الـ Service Layer هو قلب المشروع — كل الـ business logic يعيش هنا.
 * قواعد الـ Service:
 *   1. لا يعرف شيئاً عن HTTP (لا Request، لا Response)
 *   2. يستقبل data بشكل plain arrays أو DTOs
 *   3. يُرجع Models أو Collections أو primitive values
 *   4. يستخدم Transactions لكل العمليات التي تمس أكثر من جدول
 *   5. يرمي Exceptions مخصصة — لا يُرجع false أو null كـ error signal
 */
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
