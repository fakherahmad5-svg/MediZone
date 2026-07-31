<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;


class AttachmentResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'mime_type'    => $this->mime_type,
            'file_size'    => $this->file_size,
            'is_encrypted' => $this->is_encrypted,
            'download_url' => route('api.v1.patient.medical-record.attachments.download', $this->id),
            'uploaded_at'  => $this->formatDate($this->created_at),
        ];
    }
}
