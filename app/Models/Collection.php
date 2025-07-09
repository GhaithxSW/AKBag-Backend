<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = [
        'name', 'description', 'slug',
    ];

    public function albums()
    {
        return $this->hasMany(Album::class);
    }
}
