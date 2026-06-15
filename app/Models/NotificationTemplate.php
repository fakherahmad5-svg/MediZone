<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'type',
        'title_en',
        'title_ar',
        'body_en',
        'body_ar',
        'channels',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channels'  => 'array',
            'is_active' => 'boolean',
        ];
    }
}