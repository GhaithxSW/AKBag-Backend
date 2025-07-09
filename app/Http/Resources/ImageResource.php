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
            'category' => $this->category,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
        ];
    }
}
