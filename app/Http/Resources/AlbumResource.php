<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ImageResource;

class AlbumResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'collection_id' => $this->collection_id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? asset('storage/' . $this->cover_image) : null,
            'slug' => $this->slug,
            'images' => ImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
