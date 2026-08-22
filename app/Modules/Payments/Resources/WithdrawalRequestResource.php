<?php

namespace App\Modules\Payments\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\WithdrawalRequest
 */
class WithdrawalRequestResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->user_id,
            'user_name'  => $this->whenLoaded('user', fn () => trim("{$this->user->first_name} {$this->user->last_name}")),
            'amount'     => (float) $this->amount,
            'status'     => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'admin_note' => $this->admin_note,
            'processed_at' => $this->formatDate($this->processed_at),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
