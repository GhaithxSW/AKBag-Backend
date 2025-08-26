<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = [
        'name', 'description',
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
}
