<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Journal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'publisher_authors',
        'issn',
        'year',
        'call_number',
        'subject',
        'shelf_location',
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

    public function articles(): HasMany
    {
        return $this->hasMany(JournalArticle::class)->orderBy('position');
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
