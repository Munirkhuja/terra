<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImageUpload extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'source',
        'event',
        'status',
        'error_message',
        'payload',
        'result',
    ];
}
