<?php

namespace App\Modules\Notifications\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Notification
 */
class NotificationResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'type'    => $this->type,
            'status'  => $this->status,
            'title'   => $this->title,
            'body'    => $this->body,
            'data'    => $this->data,
            'is_read' => $this->is_read,
            'read_at' => $this->when($this->read_at, fn () => $this->formatDate($this->read_at)),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
