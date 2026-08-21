<?php

namespace App\Modules\Consultations\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Consultation
 */
class ConsultationListResource extends BaseResource
{
    public function toArray($request): array
    {
        $viewerId = $request->user()->id;
        $doctorUser = $this->appointment?->doctor?->user;
        $patientUser = $this->appointment?->patient?->user;
        $isDoctor = $doctorUser && $doctorUser->id === $viewerId;
        $otherUser = $isDoctor ? $patientUser : $doctorUser;

        return [
            'id'             => $this->id,
            'appointment_id' => $this->appointment_id,
            'status'         => $this->status,
            'other_party'    => $otherUser ? [
                'id'   => $otherUser->id,
                'name' => $otherUser->full_name,
            ] : null,
            'last_message' => $this->latestMessage ? [
                'content'     => $this->latestMessage->content,
                'sender_role' => $this->latestMessage->sender_role,
                'created_at'  => $this->formatDate($this->latestMessage->created_at),
            ] : null,
            'unread_count' => (int) $this->unread_count,
            'updated_at'   => $this->formatDate($this->updated_at),
        ];
    }
}
