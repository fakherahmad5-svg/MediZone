<?php

namespace App\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BaseResource
 *
 * كل API Resources ترث من هذا الـ class.
 *
 * الهدف: توحيد شكل البيانات المُرجَعة للـ client.
 * بدلاً من إرجاع model مباشرة (يكشف columns قاعدة البيانات)،
 * نمرّ البيانات عبر Resource الذي يحدد بدقة ما يظهر للـ API.
 *
 * مثال استخدام في Controller:
 *   return $this->successResponse(
 *       new PatientResource($patient),
 *       'Patient retrieved successfully'
 *   );
 */
abstract class BaseResource extends JsonResource
{
    /**
     * نُبقي الـ wrapper الافتراضي 'data' معطلاً هنا
     * لأن ApiResponse trait يتولى wrapping بشكل منتظم
     */
    public static $wrap = null;

    /**
     * تحويل timestamp لـ ISO 8601 format منتظم
     * مثال: "2025-06-01T14:30:00+03:00"
     */
    protected function formatDate(?\Carbon\Carbon $date): ?string
    {
        return $date?->toIso8601String();
    }

    /**
     * تحويل تاريخ فقط (بدون وقت)
     * مثال: "2025-06-01"
     */
    protected function formatDateOnly(?\Carbon\Carbon $date): ?string
    {
        return $date?->toDateString();
    }

    protected function whenLoaded(string $relation, callable $callback): mixed
    {
        if ($this->relationLoaded($relation)) {
            return $callback();
        }

        return $this->when(false, null);
    }
}
