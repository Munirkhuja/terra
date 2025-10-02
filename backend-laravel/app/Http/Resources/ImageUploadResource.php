<?php

namespace App\Http\Resources;

use App\Enums\EventEnum;
use App\Enums\SourceEnum;
use App\Enums\StatusEnum;
use App\Models\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

class ImageUploadResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="ImageUploadResource",
     *     type="object",
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="title", type="string", example="My uploaded image"),
     *     @OA\Property(property="description", type="string", example="Example description"),
     *     @OA\Property(property="source", type="string", example="api"),
     *     @OA\Property(property="source_name", type="string", example="Api"),
     *     @OA\Property(property="event", type="string", example="get_coordinate"),
     *     @OA\Property(property="event_name", type="string", example="Получить координаты"),
     *     @OA\Property(property="status", type="string", example="processing"),
     *     @OA\Property(property="status_name", type="string", example="В обработке"),
     *     @OA\Property(property="error_message", type="string", nullable=true, example=null),
     *     @OA\Property(property="metadata", type="string", nullable=true, example={"exif":"data"}),
     *     @OA\Property(property="result", type="string", nullable=true, example=null),
     *     @OA\Property(property="latitude", type="number", format="float", example=40.12345),
     *     @OA\Property(property="longitude", type="number", format="float", example=69.12345),
     *     @OA\Property(property="image", type="string", format="url", example="https://example.com/storage/images/1.jpg")
     * )
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'source' => $this->source,
            'source_name' => SourceEnum::from($this->source)->label(),
            'event' => $this->event,
            'event_name' => EventEnum::from($this->event)->label(),
            'status' => $this->status,
            'status_name' => StatusEnum::from($this->status)->label(),
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'result' => $this->result,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image' => $this->getFirstMediaUrl(ImageUpload::IMAGE_COLLECTION)
        ];
    }
}
