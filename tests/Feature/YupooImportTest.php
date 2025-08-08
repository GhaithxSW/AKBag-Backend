<?php

namespace Tests\Feature;

use App\Services\YupooService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YupooImportTest extends TestCase
{
    use RefreshDatabase;

    protected $yupooService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yupooService = app(YupooService::class);
        Storage::fake('public');
    }

    /** @test */
    public function it_can_fetch_albums()
    {
        $albums = $this->yupooService->fetchAlbums('https://297228164.x.yupoo.com/albums', 1, 1);
        
        $this->assertIsArray($albums);
        $this->assertNotEmpty($albums, 'No albums were returned');
        
        $firstAlbum = $albums[0];
        $this->assertArrayHasKey('title', $firstAlbum);
        $this->assertArrayHasKey('url', $firstAlbum);
    }

    /** @test */
    public function it_can_fetch_album_images()
    {
        // First get an album URL
        $albums = $this->yupooService->fetchAlbums('https://297228164.x.yupoo.com/albums', 1, 1);
        $this->assertNotEmpty($albums, 'No albums found to test with');
        
        $albumUrl = $albums[0]['url'] ?? null;
        $this->assertNotNull($albumUrl, 'Album URL is missing');
        
        // Now test fetching images for this album
        $images = $this->yupooService->fetchAlbumImages($albumUrl);
        
        $this->assertIsArray($images);
        $this->assertNotEmpty($images, 'No images found in album');
        
        $firstImage = $images[0];
        $this->assertArrayHasKey('title', $firstImage);
        $this->assertArrayHasKey('url', $firstImage);
    }

    /** @test */
    public function it_can_download_image()
    {
        // First get an album with images
        $albums = $this->yupooService->fetchAlbums('https://297228164.x.yupoo.com/albums', 1, 1);
        $this->assertNotEmpty($albums, 'No albums found to test with');
        
        $albumUrl = $albums[0]['url'] ?? null;
        $this->assertNotNull($albumUrl, 'Album URL is missing');
        
        $images = $this->yupooService->fetchAlbumImages($albumUrl);
        $this->assertNotEmpty($images, 'No images found in album');
        
        $imageUrl = $images[0]['url'] ?? null;
        $this->assertNotNull($imageUrl, 'Image URL is missing');
        
        // Test downloading the image
        $path = $this->yupooService->downloadImage($imageUrl, 'test');
        
        $this->assertIsString($path);
        $this->assertNotEmpty($path);
        
        // Verify the file exists in storage
        Storage::disk('public')->assertExists($path);
    }
}
