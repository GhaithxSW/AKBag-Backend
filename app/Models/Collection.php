<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Collection extends Model
{
    protected $fillable = [
        'name', 'description', 'cover_image',
    ];

    public function canBeDeleted(): bool
    {
        return $this->albums()->count() === 0;
    }

    public function getDeletionBlockReason(): string
    {
        $albumCount = $this->albums()->count();

        return "Cannot delete collection. It has {$albumCount} album(s). Please remove or reassign all albums first.";
    }

    public function albums()
    {
        return $this->hasMany(Album::class);
    }

    /**
     * Get the cover image URL attribute.
     */
    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? Storage::disk('s3')->url($this->cover_image) : null;
    }
}
