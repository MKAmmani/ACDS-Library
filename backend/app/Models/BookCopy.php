<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCopy extends Model
{
    protected $fillable = [
        'book_id',
        'copy_number',
        'accession_number',
    ];

    protected function casts(): array
    {
        return [
            'copy_number' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
