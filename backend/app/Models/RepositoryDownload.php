<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepositoryDownload extends Model
{
    protected $fillable = ['repository_id', 'user_id'];
}
