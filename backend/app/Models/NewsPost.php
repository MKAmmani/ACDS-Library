<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsPost extends Model
{
    protected $fillable = [
        'tag',
        'title',
        'body',
        'icon',
        'tone',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
