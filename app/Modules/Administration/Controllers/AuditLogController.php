<?php

namespace App\Modules\Administration\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AuditLogController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->input('entity_type')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest('id');

        return $this->paginatedResponse(
            $query->paginate($request->integer('per_page', 20)),
            'Audit logs retrieved successfully.',
            fn (AuditLog $log) => [
                'id'          => $log->id,
                'user_id'     => $log->user_id,
                'action'      => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id'   => $log->entity_id,
                'old_values'  => $log->old_values,
                'new_values'  => $log->new_values,
                'ip_address'  => $log->ip_address,
                'created_at'  => $log->created_at,
            ]
        );
    }
}
