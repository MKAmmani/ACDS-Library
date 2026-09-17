<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EResource extends Model
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
        'call_number',
        'shelf_location',
        'subject_area',
        'language',
        'format',
        'cover_image',
        'file_path',
        'file_size',
        'file_type',
    ];

    protected $appends = ['cover_image_url', 'has_file'];

    protected function casts(): array
    {
        return [
            'year'      => 'integer',
            'file_size' => 'integer',
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
        return ! empty($this->file_path);
    }
}
