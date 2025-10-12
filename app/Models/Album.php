<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Album extends Model
{
    protected $fillable = [
        'collection_id', 'title', 'description', 'cover_image', 'sort_order',
    ];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Get the cover image URL attribute.
     */
    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? Storage::disk('s3')->url($this->cover_image) : null;
    }
}
