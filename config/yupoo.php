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

        // Delay between requests in seconds (reduced for faster processing)
        'request_delay' => (int) env('YUPOO_REQUEST_DELAY', 1),

        // Delay between image downloads in microseconds (reduced from 500ms to 100ms)
        'image_download_delay' => (int) env('YUPOO_IMAGE_DELAY', 100000),

        // Batch processing settings for performance optimization
        'batch_size' => (int) env('YUPOO_BATCH_SIZE', 8),
        'concurrent_downloads' => (int) env('YUPOO_CONCURRENT_DOWNLOADS', 5),
        'bulk_insert_size' => (int) env('YUPOO_BULK_INSERT_SIZE', 20),

        // Skip duplicate checking for faster imports (useful for clean imports)
        'skip_duplicate_check' => (bool) env('YUPOO_SKIP_DUPLICATE_CHECK', false),

        // Progress reporting interval (every N items)
        'progress_interval' => (int) env('YUPOO_PROGRESS_INTERVAL', 10),

        // Pagination settings
        'max_pages_per_album' => (int) env('YUPOO_MAX_PAGES_PER_ALBUM', 50),
        'max_empty_pages' => (int) env('YUPOO_MAX_EMPTY_PAGES', 3),
        'page_request_delay' => (int) env('YUPOO_PAGE_REQUEST_DELAY', 100000), // microseconds
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
        'covers' => 'albums/covers',
        'images' => 'images',
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
        'timeout' => (int) env('YUPOO_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('YUPOO_HTTP_CONNECT_TIMEOUT', 10),
        'verify' => (bool) env('YUPOO_HTTP_VERIFY_SSL', false), // Set to true in production
        'retry_times' => (int) env('YUPOO_HTTP_RETRY_TIMES', 3),
        'retry_sleep' => (int) env('YUPOO_HTTP_RETRY_SLEEP', 1000), // milliseconds

        // Connection pooling and performance settings
        'pool_size' => (int) env('YUPOO_HTTP_POOL_SIZE', 10),
        'max_redirects' => (int) env('YUPOO_HTTP_MAX_REDIRECTS', 3),

        // Headers for better compatibility and performance
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.8,zh-CN;q=0.6,zh;q=0.5',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ],
    ],
];
