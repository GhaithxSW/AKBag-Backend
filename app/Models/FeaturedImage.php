<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        return $this->image_path ? asset('storage/'.str_replace('public/', '', $this->image_path)) : null;
    }

    public static function getActiveImages()
    {
        return self::where('is_active', true)
            ->orderBy('position')
            ->get();
    }
}
