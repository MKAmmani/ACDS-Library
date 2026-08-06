<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningHour extends Model
{
    protected $fillable = [
        'day_label',
        'time_label',
        'is_closed',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_closed'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
