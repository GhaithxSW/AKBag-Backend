<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
        'album_id', 'title', 'category', 'image_path',
    ];

    public function album()
    {
        return $this->belongsTo(Album::class);
    }
}
