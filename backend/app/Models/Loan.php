<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = [
        'book_id',
        'user_id',
        'borrowed_at',
        'due_date',
        'returned_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at'  => 'datetime',
            'due_date'     => 'date',
            'returned_at'  => 'datetime',
        ];
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fine()
    {
        return $this->hasOne(Fine::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'active' && $this->due_date->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')->whereDate('due_date', '<', now());
    }
}
