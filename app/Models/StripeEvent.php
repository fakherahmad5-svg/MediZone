<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeEvent extends Model
{
    protected $fillable = [
        'stripe_event_id',
        'type',
        'processed_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'processed_at'  => 'datetime',
            'payload'       => 'array',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function markProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }
}