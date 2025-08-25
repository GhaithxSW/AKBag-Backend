<?php

/**
 * Yupoo Page Analyzer
 *
 * This script analyzes the structure of a Yupoo page to extract album and image data.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load the saved Yupoo page content
$yupooContent = file_get_contents('yupoo_final_response.html');

if ($yupooContent === false) {
    exit("Error: Could not read yupoo_final_response.html\n");
}

// Function to log messages with timestamp
function log_message($message, $data = null)
{
    $output = '['.date('Y-m-d H:i:s')."] $message";
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $output .= "\n".json_encode($data, JSON_PRETTY_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $output .= " $data";
        }
    }
    echo $output."\n";
}

// 1. Check for JSON data in the page
log_message('=== Checking for JSON data ===');
$jsonData = [];
if (preg_match('/<script[^>]*>\s*window\.__INITIAL_STATE__\s*=\s*({.+?})\s*<\/script>/is', $yupooContent, $matches)) {
    $jsonData = json_decode($matches[1], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        log_message('Found JSON data in the page');
        file_put_contents('yupoo_initial_state.json', json_encode($jsonData, JSON_PRETTY_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        log_message('JSON data saved to: yupoo_initial_state.json');

        // Check for album data in JSON
        if (isset($jsonData['albumList']['list'])) {
            $albumCount = count($jsonData['albumList']['list']);
            log_message("Found $albumCount albums in JSON data");

            // Extract album information
            $albums = [];
            foreach ($jsonData['albumList']['list'] as $album) {
                $albums[] = [
                    'id' => $album['id'] ?? null,
                    'name' => $album['name'] ?? null,
                    'photo_count' => $album['photo_count'] ?? 0,
                    'cover_url' => $album['cover_url'] ?? null,
                    'link' => $album['link'] ?? null,
                ];
            }

            file_put_contents('yupoo_albums.json', json_encode($albums, JSON_PRETTY_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            log_message('Album information saved to: yupoo_albums.json');
        }
    } else {
        log_message('Error decoding JSON: '.json_last_error_msg());
    }
} else {
    log_message('No JSON data found in the page');
}

// 2. Extract album links from HTML
log_message("\n=== Extracting album links from HTML ===");
$albumLinks = [];
if (preg_match_all('/<a[^>]+href=["\']([^"\']+albums\/\d+)["\']/i', $yupooContent, $matches)) {
    $albumLinks = array_unique($matches[1]);
    log_message('Found '.count($albumLinks).' album links in HTML');

    // Save album links
    file_put_contents('yupoo_album_links.txt', implode("\n", $albumLinks));
    log_message('Album links saved to: yupoo_album_links.txt');

    // Display first few album links
    $sampleCount = min(3, count($albumLinks));
    log_message('Sample album links:', array_slice($albumLinks, 0, $sampleCount));
} else {
    log_message('No album links found in HTML');
}

// 3. Extract image URLs
log_message("\n=== Extracting image URLs ===");
$imageUrls = [];
if (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))[^"\']*["\']/i', $yupooContent, $matches)) {
    $imageUrls = array_unique($matches[1]);
    log_message('Found '.count($imageUrls).' image URLs in HTML');

    // Filter out common non-album images (logos, icons, etc.)
    $filteredImageUrls = array_filter($imageUrls, function ($url) {
        $excludePatterns = [
            '/logo/i',
            '/icon/i',
            '/s\.yupoo\.com\/website\//i',
            '/\.(?:svg|gif)$/i',
        ];

        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }

        return true;
    });

    log_message('Found '.count($filteredImageUrls).' relevant image URLs after filtering');

    // Save image URLs
    file_put_contents('yupoo_image_urls.txt', implode("\n", $filteredImageUrls));
    log_message('Image URLs saved to: yupoo_image_urls.txt');

    // Display first few image URLs
    $sampleCount = min(3, count($filteredImageUrls));
    log_message('Sample image URLs:', array_slice($filteredImageUrls, 0, $sampleCount));
} else {
    log_message('No image URLs found in HTML');
}

// 4. Check for pagination
log_message("\n=== Checking for pagination ===");
if (preg_match('/<div[^>]+class=["\'][^"\']*pagination[^"\']*["\'][^>]*>.*<\/div>/is', $yupooContent, $matches)) {
    log_message('Pagination found in the page');

    // Extract page numbers
    if (preg_match_all('/<a[^>]+href=["\'][^"\']*page=(\d+)[^"\']*["\'][^>]*>\d+<\/a>/i', $matches[0], $pageMatches)) {
        $pageNumbers = array_map('intval', $pageMatches[1]);
        $totalPages = max($pageNumbers);
        log_message("Found $totalPages pages of results");
    }
} else {
    log_message('No pagination found in the page');
}

// 5. Extract metadata
log_message("\n=== Extracting metadata ===");
$metadata = [
    'title' => '',
    'description' => '',
    'keywords' => '',
];

// Extract title
if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $yupooContent, $matches)) {
    $metadata['title'] = trim($matches[1]);
}

// Extract meta description
if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $yupooContent, $matches)) {
    $metadata['description'] = trim($matches[1]);
}

// Extract meta keywords
if (preg_match('/<meta[^>]+name=["\']keywords["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $yupooContent, $matches)) {
    $metadata['keywords'] = trim($matches[1]);
}

log_message('Page metadata:', $metadata);

log_message("\nAnalysis complete. Check the generated files for more details.");
