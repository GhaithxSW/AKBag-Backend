<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'collection_id' => $this->collection_id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? asset('storage/'.$this->cover_image) : null,
            'images' => ImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
