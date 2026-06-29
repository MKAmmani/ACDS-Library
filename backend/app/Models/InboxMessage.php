<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    protected $fillable = ['thread_id', 'sender_id', 'sender_name', 'body'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(InboxThread::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
