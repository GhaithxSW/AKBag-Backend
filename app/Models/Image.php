<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Get the full S3 URL for the image.
     */
    public function getImageUrlAttribute(): string
    {
        if (!$this->image_path) {
            return '';
        }
        
        // Check if we're using S3 storage
        if (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') {
            // Try to get S3 URL, fallback to manual generation if needed
            $url = Storage::disk('s3')->url($this->image_path);
            
            // If the URL doesn't contain the S3 domain, generate it manually
            if (!str_contains($url, 'amazonaws.com') && !str_contains($url, 's3.')) {
                $bucket = config('filesystems.disks.s3.bucket');
                $region = config('filesystems.disks.s3.region');
                $url = "https://{$bucket}.s3.{$region}.amazonaws.com/{$this->image_path}";
            }
            
            return $url;
        }
        
        // For local storage, use asset helper
        return asset('storage/' . $this->image_path);
    }

    /**
     * Get the image path for display (backward compatibility).
     */
    public function getDisplayImagePathAttribute(): string
    {
        return $this->image_url;
    }

}
