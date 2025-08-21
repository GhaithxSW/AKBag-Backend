<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'album_id',
        'title',
        'image_path',
        'description',
        'original_url',
    ];

    /**
     * Get the album that owns the image.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

}
