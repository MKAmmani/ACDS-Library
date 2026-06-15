<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'subject',
        'isbn',
        'format',
        'status',
        'description',
        'publication_year',
        'language',
        'cover_image',
        'file_path',
    ];

    protected $appends = ['cover_image_url', 'has_file'];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
        ];
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image
            ? Storage::disk('public')->url($this->cover_image)
            : null;
    }

    public function getHasFileAttribute(): bool
    {
        return ! is_null($this->file_path);
    }
}
