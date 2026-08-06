<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acquisition extends Model
{
    protected $fillable = [
        'title', 'authors', 'requested_by', 'estimated_cost',
        'copies', 'status', 'notes', 'user_id', 'book_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'copies'         => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
