<?php

namespace App\Modules\Payments\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Wallet
 */
class WalletResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'balance'     => (float) $this->balance,
            'currency'    => $this->currency,
            'is_platform' => (bool) $this->is_platform,
            'updated_at'  => $this->formatDate($this->updated_at),
        ];
    }
}
