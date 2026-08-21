<?php

namespace App\Modules\Consultations\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\ConsultationMessage
 */
class ConsultationMessageResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'consultation_id'  => $this->consultation_id,
            'sender_id'        => $this->sender_id,
            'sender_role'      => $this->sender_role,
            'sender_name'      => $this->sender?->full_name,
            'message_type'     => $this->message_type,
            'content'          => $this->content,
            'is_read'          => (bool) $this->is_read,
            'read_at'          => $this->formatDate($this->read_at),
            'created_at'       => $this->formatDate($this->created_at),
        ];
    }
}
