<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cover_image' => $this->cover_image,
            'cover_image_url' => $this->cover_image_url,
            'albums_count' => $this->when(isset($this->albums_count), $this->albums_count),
            'albums' => AlbumResource::collection($this->whenLoaded('albums')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
