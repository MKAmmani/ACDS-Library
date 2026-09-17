<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalArticle extends Model
{
    protected $fillable = [
        'journal_id',
        'title',
        'authors',
        'page_range',
        'position',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
