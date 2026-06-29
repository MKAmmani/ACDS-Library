<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InboxThread extends Model
{
    protected $fillable = [
        'channel',
        'subject',
        'query_type',
        'from_user_id',
        'from_name',
        'status',
        'last_sender_role',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'thread_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(InboxMessage::class, 'thread_id')->latestOfMany();
    }

    // Display name: registered user name or walk-in name
    public function getSenderNameAttribute(): string
    {
        return $this->fromUser?->name ?? $this->from_name ?? 'Unknown';
    }
}
