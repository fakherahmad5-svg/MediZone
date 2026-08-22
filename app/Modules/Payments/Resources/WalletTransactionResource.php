<?php

namespace App\Modules\Payments\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\WalletTransaction
 */
class WalletTransactionResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'amount'        => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'description'   => $this->description,
            'related_type'  => $this->related_type ? class_basename($this->related_type) : null,
            'related_id'    => $this->related_id,
            'created_at'    => $this->formatDate($this->created_at),
        ];
    }
}
