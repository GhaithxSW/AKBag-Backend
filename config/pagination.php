<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Pagination Settings
    |--------------------------------------------------------------------------
    |
    | These values define the default pagination behavior for the API endpoints.
    | You can override these in your controllers or through query parameters.
    |
    */

    'default_per_page' => env('PAGINATION_DEFAULT_PER_PAGE', 15),
    'max_per_page' => env('PAGINATION_MAX_PER_PAGE', 100),
    'min_per_page' => env('PAGINATION_MIN_PER_PAGE', 1),

    /*
    |--------------------------------------------------------------------------
    | Sorting Options
    |--------------------------------------------------------------------------
    |
    | Default sorting configuration for API endpoints
    |
    */

    'default_sort_column' => 'id',
    'default_sort_order' => 'desc',
    'allowed_sort_orders' => ['asc', 'desc'],
];
