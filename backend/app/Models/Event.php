<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'starts_at',
        'time_label',
        'place',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }
}
