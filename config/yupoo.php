<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Yupoo Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Yupoo importer.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the Yupoo account to import from.
    |
    */
    'base_url' => env('YUPOO_BASE_URL', 'https://297228164.x.yupoo.com'),

    /*
    |--------------------------------------------------------------------------
    | Import Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the import behavior.
    |
    */
    'import' => [
        // Maximum number of albums to import (0 for no limit)
        'max_albums' => (int) env('YUPOO_MAX_ALBUMS', 50),
        
        // Number of albums to fetch per page
        'albums_per_page' => (int) env('YUPOO_ALBUMS_PER_PAGE', 20),
        
        // Delay between requests in seconds (to avoid rate limiting)
        'request_delay' => (int) env('YUPOO_REQUEST_DELAY', 2),
        
        // Delay between image downloads in microseconds (500000 = 0.5 seconds)
        'image_download_delay' => (int) env('YUPOO_IMAGE_DELAY', 500000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Paths
    |--------------------------------------------------------------------------
    |
    | These paths are relative to the storage/app/public directory.
    |
    */
    'storage' => [
        'covers' => 'yupoo/covers',
        'images' => 'yupoo/images',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    |
    | Default values for imported content.
    |
    */
    'defaults' => [
        'collection' => [
            'name' => 'Yupoo Import',
            'description' => 'Albums imported from Yupoo',
            'slug' => 'yupoo-import',
        ],
        'album' => [
            'description' => 'Imported from Yupoo',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the HTTP client used to make requests to Yupoo.
    |
    */
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify' => false, // Set to true in production with proper SSL certificate
        'retry_times' => 3,
        'retry_sleep' => 1000, // milliseconds
    ],
];
