<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

class UserResource extends JsonResource
{
    /**
     * @OA\Schema(
     *     schema="UserResource",
     *     type="object",
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="name", type="string", example="John Doe"),
     *     @OA\Property(property="email", type="string", example="john@example.com"),
     *     @OA\Property(property="avatar", type="string", example="path.jpg"),
     *     @OA\Property(property="role", type="string", example="Admin"),
     *     @OA\Property(property="created_at", type="string", example="2025-09-23 22:02:34")
     * )
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $this->moonshineUserRole?->name,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
