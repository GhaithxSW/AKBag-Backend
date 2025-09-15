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
            'cover_image' => $this->cover_image,
            'cover_image_url' => $this->cover_image_url,
            'images_count' => $this->when(isset($this->images_count), $this->images_count),
            'collection' => new CollectionResource($this->whenLoaded('collection')),
            'images' => ImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
