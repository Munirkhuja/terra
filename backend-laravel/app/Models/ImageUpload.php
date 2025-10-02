<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ImageUpload extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;

    public const IMAGE_COLLECTION = 'image_collection';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'source',
        'event',
        'status',
        'error_message',
        'metadata',
        'result',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'error_message' => 'array',
        'metadata' => 'array',
        'result' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE_COLLECTION)->singleFile();
    }
}
