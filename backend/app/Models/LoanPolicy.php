<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPolicy extends Model
{
    protected $fillable = [
        'membership_type',
        'max_books',
        'loan_days',
        'fine_per_day',
        'max_fine',
    ];

    protected function casts(): array
    {
        return [
            'max_books'    => 'integer',
            'loan_days'    => 'integer',
            'fine_per_day' => 'decimal:2',
            'max_fine'     => 'decimal:2',
        ];
    }

    public static function forUser(User $user): self
    {
        return static::where('membership_type', $user->membership_type)->firstOrFail();
    }
}
