<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'title',
        'kind',
        'tag',
        'duration_label',
        'thumbnail_url',
        'thumbnail',
        'media_url',
        'media_path',
        'file_size',
        'mime_type',
        'views',
        'published_at',
    ];

    protected $appends = ['thumbnail_display_url', 'has_media_file', 'is_audio'];

    protected function casts(): array
    {
        return [
            'views'        => 'integer',
            'file_size'    => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * An uploaded thumbnail (stored on the public disk) always wins over a
     * pasted external image URL.
     */
    public function getThumbnailDisplayUrlAttribute(): ?string
    {
        if ($this->thumbnail) {
            return Storage::disk('public')->url($this->thumbnail);
        }

        return $this->thumbnail_url;
    }

    public function getHasMediaFileAttribute(): bool
    {
        return ! is_null($this->media_path);
    }

    /**
     * Whether the uploaded file is an audio-only recording (podcast, oral
     * history interview, …) rather than a video. Prefers the detected
     * mime_type captured at upload time; falls back to the file extension
     * for rows uploaded before that column existed.
     */
    public function getIsAudioAttribute(): bool
    {
        if ($this->mime_type) {
            return str_starts_with($this->mime_type, 'audio/');
        }

        if (! $this->media_path) {
            return false;
        }

        $ext = strtolower(pathinfo($this->media_path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp3', 'wav', 'm4a', 'aac', 'flac', 'oga', 'opus', 'wma'], true);
    }
}
