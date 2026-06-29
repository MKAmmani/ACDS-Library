<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'authors',
        'publisher',
        'year',
        'isbn',
        'edition',
        'description',
        'subject_area',
        'call_number',
        'shelf_location',
        'language',
        'format',
        'material_type',
        'number_of_copies',
        'available_copies',
        'cover_treatment',
    ];

    protected function casts(): array
    {
        return [
            'year'             => 'integer',
            'number_of_copies' => 'integer',
            'available_copies' => 'integer',
        ];
    }

    public function copies()
    {
        return $this->hasMany(BookCopy::class)->orderBy('copy_number');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
