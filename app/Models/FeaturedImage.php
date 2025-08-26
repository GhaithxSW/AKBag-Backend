<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FeaturedImage extends Model
{
    protected $fillable = [
        'title',
        'image_path',
        'description',
        'position',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getImageUrlAttribute()
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public static function getActiveImages()
    {
        return self::where('is_active', true)
            ->orderBy('position')
            ->get();
    }
}
