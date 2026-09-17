<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDownload extends Model
{
    protected $fillable = ['journal_id', 'user_id'];
}
