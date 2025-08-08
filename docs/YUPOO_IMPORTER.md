# Yupoo Importer Documentation

## Overview
The Yupoo Importer is a powerful tool that allows you to import albums and images from Yupoo (https://297228164.x.yupoo.com) into your Laravel application. It handles everything from fetching albums and images to downloading and storing them in your storage.

## Features

- **Bulk Import**: Import multiple albums and images in one go
- **Automatic Categorization**: Assign categories to imported images
- **Duplicate Prevention**: Skip already imported content
- **Error Handling**: Comprehensive logging and error reporting
- **Progress Tracking**: Real-time progress updates during import
- **Image Processing**: Automatic resizing and optimization

## Prerequisites

- PHP 8.0+
- Laravel 9.0+
- Guzzle HTTP Client
- Symfony DOM Crawler
- Fileinfo PHP extension (for image processing)

## Installation

1. Ensure all dependencies are installed:
   ```bash
   composer require guzzlehttp/guzzle symfony/dom-crawler
   ```

2. Publish the configuration file (if not already done):
   ```bash
   php artisan vendor:publish --tag=config
   ```

3. Configure your Yupoo settings in `config/yupoo.php`:
   ```php
   return [
       'base_url' => 'https://297228164.x.yupoo.com',
       'import' => [
           'max_albums' => 50,           // Maximum number of albums to import
           'albums_per_page' => 20,      // Albums per page to fetch
           'request_delay' => 2,         // Delay between requests (in seconds)
           'image_download_delay' => 500, // Delay between image downloads (in milliseconds)
       ],
       'storage' => [
           'covers' => 'yupoo/covers',   // Storage path for album covers
           'images' => 'yupoo/images',   // Storage path for album images
       ],
   ];
   ```

## Usage

### Basic Import

To import all albums and images:

```bash
php artisan yupoo:import
```

### Advanced Options

```bash
# Import a specific number of albums
php artisan yupoo:import --max=10

# Start from a specific page
php artisan yupoo:import --page=2

# Import from a specific Yupoo URL
php artisan yupoo:import --url=https://297228164.x.yupoo.com/albums

# Force re-download of existing images
php artisan yupoo:import --force

# Run in verbose mode for detailed output
php artisan yupoo:import -v

# Run in debug mode for maximum verbosity
php artisan yupoo:import -vvv
```

### Testing the Connection

Before running a full import, test the connection to Yupoo:

```bash
php artisan yupoo:test -v
```

This will:
1. Test the connection to Yupoo
2. Fetch a list of albums
3. Display the first album details
4. Fetch and display images from the first album
5. Test downloading a sample image

## Image Categories

The importer supports automatic categorization of images. Here's how it works:

### Category Assignment

1. **Automatic Assignment**: The importer will automatically create categories based on album names
2. **Manual Assignment**: You can manually assign categories in the admin panel
3. **Default Category**: Images without a specific category will be assigned to the 'Uncategorized' category

### Managing Categories

Categories can be managed through the Filament admin panel:

1. Navigate to `/admin/categories`
2. Add, edit, or delete categories as needed
3. Assign categories to images in the Images section

## Troubleshooting

### Common Issues

1. **Connection Timeouts**:
   - Increase timeout settings in `config/yupoo.php`
   - Check your internet connection
   - Verify the Yupoo URL is accessible

2. **Missing Images**:
   - Check storage permissions
   - Verify the storage disk is properly configured
   - Run with `-vvv` flag for detailed error messages

3. **Import Stuck**:
   - The importer might be rate-limited by Yupoo
   - Try increasing the request delay in the config
   - Run the import during off-peak hours

### Logs

Detailed logs are available in `storage/logs/laravel.log`. You can also view real-time logs with:

```bash
tail -f storage/logs/laravel.log
```

## Best Practices

1. **Test First**: Always run a test import with a small number of albums first
2. **Monitor Resources**: Large imports can be resource-intensive
3. **Use Queues**: For large imports, consider using Laravel queues
4. **Regular Backups**: Always backup your database before running large imports
5. **Update Regularly**: Keep the importer updated for the latest features and fixes

## API Reference

### YupooService

```php
// Fetch albums from Yupoo
$albums = $yupooService->fetchAlbums($url, $page = 1, $limit = null);

// Fetch images from an album
$images = $yupooService->fetchAlbumImages($albumUrl);

// Download an image
$path = $yupooService->downloadImage($imageUrl, $type = 'images');
```

### Events

The importer dispatches the following events:

- `AlbumImported`: When an album is successfully imported
- `ImageImported`: When an image is successfully imported
- `ImportCompleted`: When the import process is complete
- `ImportFailed`: When an error occurs during import

## Contributing

Contributions are welcome! Please read the [contributing guide](CONTRIBUTING.md) for details.

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).
