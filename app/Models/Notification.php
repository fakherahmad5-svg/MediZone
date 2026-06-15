<?php


namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'title',
        'body',
        'data',
        'channels_sent',
        'is_read',
        'read_at',
        'sent_at',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'data'          => 'array',
            'channels_sent' => 'array',
            'is_read'       => 'boolean',
            'read_at'       => 'datetime',
            'sent_at'       => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}