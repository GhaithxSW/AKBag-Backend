<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'album_id' => $this->album_id,
            'title' => $this->title,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'original_url' => $this->original_url,
            'album' => new AlbumResource($this->whenLoaded('album')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
