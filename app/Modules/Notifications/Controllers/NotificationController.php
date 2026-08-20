<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Core\Services\NotificationService;
use App\Models\Notification;
use App\Modules\Notifications\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class NotificationController extends BaseController
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->when($request->boolean('unread_only'), fn ($q) => $q->where('is_read', false))
            ->latest('id');

        return $this->paginatedResponse(
            $query->paginate($request->integer('per_page', 15)),
            'Notifications retrieved successfully.',
            fn ($n) => (new NotificationResource($n))->resolve($request)
        );
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->resolveOwned($request, $id);

        $notification = $this->notifications->markAsRead($notification);

        return $this->successResponse(new NotificationResource($notification), 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllAsRead($request->user());

        return $this->successResponse(['updated_count' => $count], 'All notifications marked as read.');
    }

    private function resolveOwned(Request $request, int $id): Notification
    {
        $notification = Notification::find($id);

        if (! $notification) {
            throw new NotFoundException('Notification not found.');
        }

        if ($notification->user_id !== $request->user()->id) {
            throw new AuthorizationException('This notification does not belong to you.');
        }

        return $notification;
    }
}
